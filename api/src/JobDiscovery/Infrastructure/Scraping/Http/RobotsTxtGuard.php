<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Http;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RobotsTxtGuard
{
    private const CACHE_TTL_SECONDS = 86_400;
    private const MAX_REDIRECTS = 5;
    private const MAX_RESPONSE_BYTES = 512_000;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $cacheDirectory,
    ) {
    }

    public function assertAllowed(string $url, string $userAgent): RobotsTxtCheckResult
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';
        if ($scheme === '' || $host === '') {
            throw new HttpScrapingException('Impossible de déterminer l’origine pour robots.txt.');
        }

        $origin = $scheme.'://'.$host.$port;
        $loaded = $this->load($origin, $userAgent);
        if (!$this->isAllowed($loaded['body'], $url, $userAgent)) {
            throw new HttpScrapingException(sprintf('robots.txt interdit la collecte de %s.', $url));
        }

        return $loaded['result'];
    }

    /** @return array{body: string, result: RobotsTxtCheckResult} */
    private function load(string $origin, string $userAgent): array
    {
        $requestedUrl = $origin.'/robots.txt';
        $cached = $this->readCache($origin, $requestedUrl);
        if ($cached !== null) {
            return $cached;
        }

        $currentUrl = $requestedUrl;
        $redirects = 0;
        $visited = [];

        while (true) {
            if (isset($visited[$currentUrl])) {
                throw new HttpScrapingException(sprintf(
                    'Boucle de redirection détectée pendant la vérification de robots.txt (%s).',
                    $currentUrl,
                ));
            }
            $visited[$currentUrl] = true;
            $this->assertPublicUrl($currentUrl);

            $response = $this->httpClient->request('GET', $currentUrl, [
                'headers' => [
                    'Accept' => 'text/plain,*/*;q=0.1',
                    'User-Agent' => $userAgent,
                ],
                'timeout' => 5,
                'max_redirects' => 0,
            ]);
            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders(false);

            if (in_array($statusCode, [301, 302, 303, 307, 308], true)) {
                $location = $this->firstHeader($headers, 'location');
                if ($location === null) {
                    throw new HttpScrapingException(sprintf(
                        'robots.txt a répondu HTTP %d sans destination de redirection.',
                        $statusCode,
                    ));
                }
                if ($redirects >= self::MAX_REDIRECTS) {
                    throw new HttpScrapingException(sprintf(
                        'robots.txt dépasse la limite de %d redirections.',
                        self::MAX_REDIRECTS,
                    ));
                }

                $nextUrl = $this->resolveRedirectUrl($currentUrl, $location);
                $this->assertSafeRedirect($requestedUrl, $currentUrl, $nextUrl);
                ++$redirects;
                $currentUrl = $nextUrl;
                continue;
            }

            $result = new RobotsTxtCheckResult(
                $requestedUrl,
                $currentUrl,
                $statusCode,
                $redirects,
            );

            if (in_array($statusCode, [404, 410], true)) {
                $this->writeCache($origin, '', $result);

                return ['body' => '', 'result' => $result];
            }
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new HttpScrapingException(sprintf(
                    'robots.txt est indisponible : URL finale %s, statut HTTP %d après %d redirection(s).',
                    $currentUrl,
                    $statusCode,
                    $redirects,
                ));
            }

            $body = $response->getContent(false);
            if (strlen($body) > self::MAX_RESPONSE_BYTES) {
                throw new HttpScrapingException('robots.txt dépasse la taille maximale autorisée.');
            }

            $this->writeCache($origin, $body, $result);

            return ['body' => $body, 'result' => $result];
        }
    }

    private function assertSafeRedirect(string $requestedUrl, string $currentUrl, string $nextUrl): void
    {
        $this->assertPublicUrl($nextUrl);

        $currentScheme = strtolower((string) parse_url($currentUrl, PHP_URL_SCHEME));
        $nextScheme = strtolower((string) parse_url($nextUrl, PHP_URL_SCHEME));
        if ($currentScheme === 'https' && $nextScheme !== 'https') {
            throw new HttpScrapingException('robots.txt refuse une redirection HTTPS vers HTTP.');
        }

        $requestedHost = strtolower((string) parse_url($requestedUrl, PHP_URL_HOST));
        $nextHost = strtolower((string) parse_url($nextUrl, PHP_URL_HOST));
        if (!$this->canonicalHostsMatch($requestedHost, $nextHost)) {
            throw new HttpScrapingException(sprintf(
                'robots.txt redirige vers un domaine différent non autorisé (%s → %s).',
                $requestedHost,
                $nextHost,
            ));
        }
    }

    private function canonicalHostsMatch(string $left, string $right): bool
    {
        $normalize = static fn (string $host): string => str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        return $left !== '' && $right !== '' && $normalize($left) === $normalize($right);
    }

    private function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new HttpScrapingException('robots.txt ne peut utiliser que des URL HTTP/HTTPS publiques.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new HttpScrapingException('robots.txt refuse les URL contenant des identifiants.');
        }
        if ($port !== null && !in_array($port, [80, 443], true)) {
            throw new HttpScrapingException('robots.txt refuse les ports autres que 80 et 443.');
        }
        if ($host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')) {
            throw new HttpScrapingException('robots.txt refuse les hôtes locaux ou internes.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new HttpScrapingException('robots.txt refuse les adresses IP privées ou réservées.');
        }
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
            throw new HttpScrapingException('Impossible de résoudre la redirection relative de robots.txt.');
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

    private function isAllowed(string $robots, string $url, string $userAgent): bool
    {
        if (trim($robots) === '') {
            return true;
        }

        $groups = [];
        $agents = [];
        $rules = [];
        $hasRules = false;

        foreach (preg_split('/\R/', $robots) ?: [] as $rawLine) {
            $line = trim((string) preg_replace('/\s*#.*$/', '', $rawLine));
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);
            if ($field === 'user-agent') {
                if ($hasRules) {
                    $groups[] = ['agents' => $agents, 'rules' => $rules];
                    $agents = [];
                    $rules = [];
                    $hasRules = false;
                }
                $agents[] = strtolower($value);
                continue;
            }

            if (($field === 'allow' || $field === 'disallow') && $agents !== []) {
                $rules[] = ['type' => $field, 'path' => $value];
                $hasRules = true;
            }
        }

        if ($agents !== []) {
            $groups[] = ['agents' => $agents, 'rules' => $rules];
        }

        $normalizedAgent = strtolower($userAgent);
        $matching = [];
        $bestSpecificity = -1;
        foreach ($groups as $group) {
            $specificity = -1;
            foreach ($group['agents'] as $agent) {
                if ($agent === '*') {
                    $specificity = max($specificity, 0);
                } elseif ($agent !== '' && str_contains($normalizedAgent, $agent)) {
                    $specificity = max($specificity, strlen($agent));
                }
            }

            if ($specificity > $bestSpecificity) {
                $matching = $specificity >= 0 ? [$group] : [];
                $bestSpecificity = $specificity;
            } elseif ($specificity >= 0 && $specificity === $bestSpecificity) {
                $matching[] = $group;
            }
        }

        if ($matching === []) {
            return true;
        }

        $parts = parse_url($url);
        $path = (string) ($parts['path'] ?? '/');
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?'.$parts['query'];
        }

        $winner = null;
        foreach ($matching as $group) {
            foreach ($group['rules'] as $rule) {
                $rulePath = (string) $rule['path'];
                if ($rulePath === '') {
                    continue;
                }
                if (!$this->matchesRule($path, $rulePath)) {
                    continue;
                }

                $specificity = strlen(str_replace(['*', '$'], '', $rulePath));
                if ($winner === null
                    || $specificity > $winner['specificity']
                    || ($specificity === $winner['specificity'] && $rule['type'] === 'allow')) {
                    $winner = ['specificity' => $specificity, 'type' => $rule['type']];
                }
            }
        }

        return $winner === null || $winner['type'] === 'allow';
    }

    private function matchesRule(string $path, string $rule): bool
    {
        $endAnchored = str_ends_with($rule, '$');
        $quoted = preg_quote($endAnchored ? substr($rule, 0, -1) : $rule, '/');
        $pattern = '/^'.str_replace('\\*', '.*', $quoted).($endAnchored ? '$' : '').'/';

        return preg_match($pattern, $path) === 1;
    }

    /** @return array{body: string, result: RobotsTxtCheckResult}|null */
    private function readCache(string $origin, string $requestedUrl): ?array
    {
        $path = $this->cachePath($origin);
        if (!is_file($path) || filemtime($path) < time() - self::CACHE_TTL_SECONDS) {
            return null;
        }

        $content = file_get_contents($path);
        if (!is_string($content)) {
            return null;
        }

        try {
            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($payload) || !is_string($payload['body'] ?? null)) {
            return null;
        }

        $finalUrl = is_string($payload['finalUrl'] ?? null) ? $payload['finalUrl'] : $requestedUrl;
        $statusCode = is_int($payload['statusCode'] ?? null) ? $payload['statusCode'] : 200;
        $redirects = is_int($payload['redirects'] ?? null) ? $payload['redirects'] : 0;

        return [
            'body' => $payload['body'],
            'result' => new RobotsTxtCheckResult(
                $requestedUrl,
                $finalUrl,
                $statusCode,
                $redirects,
                true,
            ),
        ];
    }

    private function writeCache(string $origin, string $body, RobotsTxtCheckResult $result): void
    {
        if (!is_dir($this->cacheDirectory)
            && !mkdir($concurrentDirectory = $this->cacheDirectory, 0700, true)
            && !is_dir($concurrentDirectory)) {
            throw new HttpScrapingException('Impossible de créer le cache robots.txt.');
        }

        $payload = json_encode([
            'origin' => $origin,
            'body' => $body,
            'finalUrl' => $result->finalUrl,
            'statusCode' => $result->statusCode,
            'redirects' => $result->redirects,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($this->cachePath($origin), $payload, LOCK_EX) === false) {
            throw new HttpScrapingException('Impossible d’écrire le cache robots.txt.');
        }
    }

    private function cachePath(string $origin): string
    {
        return rtrim($this->cacheDirectory, '/').'/'.hash('sha256', $origin).'.json';
    }
}
