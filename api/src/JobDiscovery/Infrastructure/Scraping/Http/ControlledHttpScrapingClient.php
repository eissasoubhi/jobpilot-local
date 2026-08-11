<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Http;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ControlledHttpScrapingClient
{
    private const DEFAULT_MAX_REQUESTS_PER_SYNC = 20;
    private const DEFAULT_DAILY_QUOTA = 200;
    private const MAX_REDIRECTS = 3;
    private const CIRCUIT_FAILURE_THRESHOLD = 3;
    private const CIRCUIT_COOLDOWN_SECONDS = 900;
    private const ACCESS_DENIED_COOLDOWN_SECONDS = 3600;

    /** @var array<string, int> */
    private array $requestsThisSync = [];

    private HttpScrapingChallengeDetector $challengeDetector;

    public function __construct(
        private HttpClientInterface $httpClient,
        private HttpScrapingStateStore $stateStore,
        private RobotsTxtGuard $robotsTxtGuard,
        ?HttpScrapingChallengeDetector $challengeDetector = null,
    ) {
        $this->challengeDetector = $challengeDetector ?? new HttpScrapingChallengeDetector();
    }

    public function fetch(HttpScrapingRequest $request): HttpScrapingResult
    {
        if (!$request->policy->complianceStatus->allowsAutomatedCollection()) {
            throw new HttpScrapingException(sprintf(
                'La politique %s interdit la collecte automatisée du connecteur %s.',
                $request->policy->complianceStatus->value,
                $request->connectorCode,
            ));
        }

        $this->assertPublicUrl($request->url);
        if ($request->policy->respectsRobotsTxt) {
            $this->robotsTxtGuard->assertAllowed($request->url, $request->userAgent);
        }

        $connectorCode = strtolower(trim($request->connectorCode));
        $state = $this->stateStore->read($connectorCode);
        $this->assertCircuitClosed($state, $connectorCode);
        $cacheKey = hash('sha256', $request->url);
        $cached = is_array($state['cache'][$cacheKey] ?? null) ? $state['cache'][$cacheKey] : null;
        $headers = [
            'Accept' => 'text/html,application/xhtml+xml,application/json;q=0.9,text/plain;q=0.5,*/*;q=0.1',
            'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.7',
            'User-Agent' => $request->userAgent,
        ];
        if (is_string($cached['etag'] ?? null) && $cached['etag'] !== '') {
            $headers['If-None-Match'] = $cached['etag'];
        }
        if (is_string($cached['lastModified'] ?? null) && $cached['lastModified'] !== '') {
            $headers['If-Modified-Since'] = $cached['lastModified'];
        }

        $lastException = null;
        $totalNetworkRequests = 0;

        for ($attempt = 1; $attempt <= $request->maxRetries + 1; ++$attempt) {
            $currentUrl = $request->url;
            $redirects = 0;

            try {
                while (true) {
                    $this->reserveRequest($connectorCode, $request, $state);
                    ++$totalNetworkRequests;

                    $response = $this->httpClient->request('GET', $currentUrl, [
                        'headers' => $headers,
                        'timeout' => $request->timeoutSeconds,
                        'max_redirects' => 0,
                    ]);
                    $statusCode = $response->getStatusCode();
                    $responseHeaders = $response->getHeaders(false);

                    if (in_array($statusCode, [301, 302, 303, 307, 308], true)) {
                        $location = $this->firstHeader($responseHeaders, 'location');
                        if ($location === null || ++$redirects > self::MAX_REDIRECTS) {
                            throw new HttpScrapingException('La chaîne de redirection du scraper est invalide ou trop longue.');
                        }

                        $currentUrl = $this->resolveRedirectUrl($currentUrl, $location);
                        $this->assertPublicUrl($currentUrl);
                        continue;
                    }

                    if ($statusCode === 304) {
                        $body = $this->decodeCachedBody($cached);
                        if ($body === null) {
                            throw new HttpScrapingException('La source a répondu 304 mais aucun contenu HTTP valide n’est en cache.');
                        }

                        $this->assertNoChallenge($connectorCode, $state, $body, $responseHeaders);
                        $this->markSuccess($connectorCode, $state);

                        return new HttpScrapingResult(
                            $currentUrl,
                            200,
                            $body,
                            $responseHeaders,
                            $totalNetworkRequests,
                            true,
                        );
                    }

                    if ($this->isRetryableStatus($statusCode) && $attempt <= $request->maxRetries) {
                        $this->sleepBeforeRetry($responseHeaders, $request, $attempt);
                        continue 2;
                    }

                    if ($statusCode < 200 || $statusCode >= 300) {
                        $this->markFailure($connectorCode, $state, $statusCode);
                        throw new HttpScrapingException(sprintf(
                            'La source %s a répondu avec le statut HTTP %d.',
                            $connectorCode,
                            $statusCode,
                        ));
                    }

                    $body = $response->getContent(false);
                    if (strlen($body) > $request->maxResponseBytes) {
                        $this->markFailure($connectorCode, $state, $statusCode);
                        throw new HttpScrapingException(sprintf(
                            'La réponse HTTP dépasse la limite de %d octets.',
                            $request->maxResponseBytes,
                        ));
                    }

                    $this->assertNoChallenge($connectorCode, $state, $body, $responseHeaders);

                    $state['cache'][$cacheKey] = [
                        'url' => $currentUrl,
                        'etag' => $this->firstHeader($responseHeaders, 'etag'),
                        'lastModified' => $this->firstHeader($responseHeaders, 'last-modified'),
                        'contentType' => $this->firstHeader($responseHeaders, 'content-type'),
                        'bodyBase64' => base64_encode($body),
                        'storedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                    ];
                    $this->markSuccess($connectorCode, $state);

                    return new HttpScrapingResult(
                        $currentUrl,
                        $statusCode,
                        $body,
                        $responseHeaders,
                        $totalNetworkRequests,
                    );
                }
            } catch (TransportExceptionInterface $exception) {
                $lastException = $exception;
                if ($attempt <= $request->maxRetries) {
                    $this->sleepMilliseconds($request->initialBackoffMilliseconds * (2 ** ($attempt - 1)));
                    continue;
                }

                $this->markFailure($connectorCode, $state);
            }
        }

        throw new HttpScrapingException(
            sprintf('La collecte HTTP de %s a échoué après %d tentative(s).', $request->url, $request->maxRetries + 1),
            previous: $lastException,
        );
    }

    /** @param array<string, mixed> $state @param array<string, list<string>> $headers */
    private function assertNoChallenge(string $connectorCode, array &$state, string $body, array $headers): void
    {
        $challenge = $this->challengeDetector->detect($body, $headers);
        if ($challenge === null) {
            return;
        }

        // Treat an anti-automation challenge like an access denial: do not cache or
        // parse it as job content and suspend the connector instead of bypassing it.
        $this->markFailure($connectorCode, $state, 403);

        throw new HttpScrapingException(sprintf(
            'Protection anti-automatisation détectée pour %s (%s). Le connecteur est temporairement suspendu ; aucun contournement automatique n’est tenté.',
            $connectorCode,
            $challenge,
        ));
    }

    /** @param array<string, mixed> $state */
    private function reserveRequest(string $connectorCode, HttpScrapingRequest $request, array &$state): void
    {
        $maxPerSync = $request->policy->maxRequestsPerSync ?? self::DEFAULT_MAX_REQUESTS_PER_SYNC;
        $dailyQuota = $request->policy->dailyQuota ?? self::DEFAULT_DAILY_QUOTA;
        $usedThisSync = $this->requestsThisSync[$connectorCode] ?? 0;

        if ($usedThisSync >= $maxPerSync) {
            throw new HttpScrapingException(sprintf(
                'Le connecteur %s a atteint sa limite de %d requêtes pour cette synchronisation.',
                $connectorCode,
                $maxPerSync,
            ));
        }
        if ((int) $state['requestsToday'] >= $dailyQuota) {
            throw new HttpScrapingException(sprintf(
                'Le connecteur %s a atteint son quota journalier de %d requêtes.',
                $connectorCode,
                $dailyQuota,
            ));
        }

        $minimumDelay = $request->policy->minimumDelayMilliseconds;
        $lastRequestAt = is_numeric($state['lastRequestAt'] ?? null) ? (float) $state['lastRequestAt'] : null;
        if ($minimumDelay > 0 && $lastRequestAt !== null) {
            $elapsedMilliseconds = (microtime(true) - $lastRequestAt) * 1000;
            if ($elapsedMilliseconds < $minimumDelay) {
                $this->sleepMilliseconds((int) ceil($minimumDelay - $elapsedMilliseconds));
            }
        }

        $this->requestsThisSync[$connectorCode] = $usedThisSync + 1;
        $state['requestsToday'] = (int) $state['requestsToday'] + 1;
        $state['lastRequestAt'] = microtime(true);
        $this->stateStore->write($connectorCode, $state);
    }

    /** @param array<string, mixed> $state */
    private function assertCircuitClosed(array $state, string $connectorCode): void
    {
        $openUntil = is_string($state['circuitOpenUntil'] ?? null) ? $state['circuitOpenUntil'] : null;
        if ($openUntil === null) {
            return;
        }

        try {
            $date = new \DateTimeImmutable($openUntil);
        } catch (\Exception) {
            return;
        }

        if ($date > new \DateTimeImmutable()) {
            throw new HttpScrapingException(sprintf(
                'Le circuit breaker du connecteur %s est ouvert jusqu’au %s.',
                $connectorCode,
                $date->format(DATE_ATOM),
            ));
        }
    }

    /** @param array<string, mixed> $state */
    private function markSuccess(string $connectorCode, array &$state): void
    {
        $state['consecutiveFailures'] = 0;
        $state['circuitOpenUntil'] = null;
        $this->stateStore->write($connectorCode, $state);
    }

    /** @param array<string, mixed> $state */
    private function markFailure(string $connectorCode, array &$state, ?int $statusCode = null): void
    {
        $state['consecutiveFailures'] = (int) ($state['consecutiveFailures'] ?? 0) + 1;
        $cooldown = null;

        if (in_array($statusCode, [401, 403], true)) {
            $cooldown = self::ACCESS_DENIED_COOLDOWN_SECONDS;
        } elseif ((int) $state['consecutiveFailures'] >= self::CIRCUIT_FAILURE_THRESHOLD) {
            $cooldown = self::CIRCUIT_COOLDOWN_SECONDS;
        }

        if ($cooldown !== null) {
            $state['circuitOpenUntil'] = (new \DateTimeImmutable(sprintf('+%d seconds', $cooldown)))->format(DATE_ATOM);
        }

        $this->stateStore->write($connectorCode, $state);
    }

    /** @param array<string, list<string>> $headers */
    private function sleepBeforeRetry(array $headers, HttpScrapingRequest $request, int $attempt): void
    {
        $retryAfter = $this->firstHeader($headers, 'retry-after');
        if ($retryAfter !== null && ctype_digit(trim($retryAfter))) {
            $this->sleepMilliseconds(min(30_000, (int) trim($retryAfter) * 1000));
            return;
        }

        $this->sleepMilliseconds($request->initialBackoffMilliseconds * (2 ** ($attempt - 1)));
    }

    private function sleepMilliseconds(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep(min($milliseconds, 30_000) * 1000);
        }
    }

    private function isRetryableStatus(int $statusCode): bool
    {
        return in_array($statusCode, [408, 425, 429, 500, 502, 503, 504], true);
    }

    /** @param array<string, list<string>> $headers */
    private function firstHeader(array $headers, string $name): ?string
    {
        $values = $headers[strtolower($name)] ?? null;
        if (!is_array($values) || !isset($values[0])) {
            return null;
        }

        $value = trim((string) $values[0]);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed>|null $cached */
    private function decodeCachedBody(?array $cached): ?string
    {
        $encoded = is_string($cached['bodyBase64'] ?? null) ? $cached['bodyBase64'] : null;
        if ($encoded === null) {
            return null;
        }

        $body = base64_decode($encoded, true);

        return is_string($body) ? $body : null;
    }

    private function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new HttpScrapingException('Seules les URL HTTP et HTTPS publiques sont autorisées.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new HttpScrapingException('Les URL contenant des identifiants sont interdites.');
        }
        if ($port !== null && !in_array($port, [80, 443], true)) {
            throw new HttpScrapingException('Seuls les ports HTTP 80 et HTTPS 443 sont autorisés.');
        }
        if ($host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')) {
            throw new HttpScrapingException('Les hôtes locaux ou internes sont interdits.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new HttpScrapingException('Les adresses IP privées ou réservées sont interdites.');
        }
    }

    private function resolveRedirectUrl(string $baseUrl, string $location): string
    {
        if (filter_var($location, FILTER_VALIDATE_URL) !== false) {
            return $location;
        }

        $base = parse_url($baseUrl);
        $scheme = (string) ($base['scheme'] ?? '');
        $host = (string) ($base['host'] ?? '');
        $port = isset($base['port']) ? ':'.(int) $base['port'] : '';
        if ($scheme === '' || $host === '') {
            throw new HttpScrapingException('Impossible de résoudre une redirection relative.');
        }

        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $scheme.'://'.$host.$port.$location;
        }

        $path = (string) ($base['path'] ?? '/');
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $scheme.'://'.$host.$port.($directory === '' ? '' : $directory).'/'.$location;
    }
}
