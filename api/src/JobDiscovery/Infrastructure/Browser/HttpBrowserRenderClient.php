<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Browser;

use App\JobDiscovery\Application\BrowserRenderClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpBrowserRenderClient implements BrowserRenderClientInterface
{
    private const MAX_HTML_BYTES = 3_000_000;

    public function __construct(
        private HttpClientInterface $httpClient,
        private BrowserWorkerConfiguration $configuration,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->configuration->isConfigured();
    }

    public function render(
        string $sourceCode,
        string $url,
        string $allowedDomain,
        bool $authorizationApproved,
        bool $robotsApproved,
    ): array {
        if (!$authorizationApproved || !$robotsApproved) {
            throw new \InvalidArgumentException('Le rendu Browser exige une autorisation et un contrôle robots.txt déjà validés.');
        }
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Le worker Browser/Playwright n’est pas configuré.');
        }

        $domain = $this->normalizeDomain($allowedDomain);
        $requestedUrl = $this->validateSourceUrl($url, $domain);
        $baseUrl = $this->configuration->baseUrl();
        $token = $this->configuration->token();
        if ($baseUrl === null || $token === null) {
            throw new \RuntimeException('Le worker Browser/Playwright n’est pas configuré.');
        }

        try {
            $response = $this->httpClient->request('POST', $baseUrl.'/render', [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'sourceCode' => mb_substr(trim($sourceCode), 0, 120),
                    'url' => $requestedUrl,
                    'allowedDomain' => $domain,
                    'authorizationApproved' => true,
                    'robotsApproved' => true,
                    'timeoutMs' => 10_000,
                    'settleMs' => 800,
                    'maxHtmlBytes' => self::MAX_HTML_BYTES,
                ],
                'timeout' => 20,
            ]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                $payload = $response->toArray(false);
                $message = is_array($payload) && is_string($payload['error'] ?? null)
                    ? trim($payload['error'])
                    : '';
                throw new \RuntimeException($message !== '' ? $message : sprintf('Le worker Browser a répondu HTTP %d.', $status));
            }

            $payload = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('Le worker Browser est indisponible ou a renvoyé une réponse invalide.', 0, $exception);
        }

        if (!is_array($payload)) {
            throw new \RuntimeException('La réponse du worker Browser est invalide.');
        }

        $finalUrl = $this->validateSourceUrl((string) ($payload['finalUrl'] ?? ''), $domain);
        $html = (string) ($payload['html'] ?? '');
        $htmlBytes = strlen($html);
        if ($html === '' || $htmlBytes > self::MAX_HTML_BYTES) {
            throw new \RuntimeException('Le HTML rendu par le worker Browser est vide ou dépasse la limite autorisée.');
        }

        $reportedBytes = max(0, (int) ($payload['htmlBytes'] ?? 0));
        if ($reportedBytes !== 0 && $reportedBytes !== $htmlBytes) {
            throw new \RuntimeException('La taille du HTML déclarée par le worker Browser est incohérente.');
        }

        return [
            'requestedUrl' => $requestedUrl,
            'finalUrl' => $finalUrl,
            'statusCode' => is_numeric($payload['statusCode'] ?? null) ? (int) $payload['statusCode'] : null,
            'title' => mb_substr(trim((string) ($payload['title'] ?? '')), 0, 500),
            'html' => $html,
            'htmlBytes' => $htmlBytes,
            'allowedRequests' => max(0, (int) ($payload['allowedRequests'] ?? 0)),
            'blockedRequests' => max(0, (int) ($payload['blockedRequests'] ?? 0)),
        ];
    }

    private function normalizeDomain(string $value): string
    {
        $domain = strtolower(rtrim(trim($value), '.'));
        if ($domain === '' || $domain === 'localhost' || str_ends_with($domain, '.local') || str_contains($domain, '/') || str_contains($domain, ':')) {
            throw new \InvalidArgumentException('Le domaine Browser autorisé est invalide.');
        }

        return $domain;
    }

    private function validateSourceUrl(string $value, string $domain): string
    {
        $value = trim($value);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Une URL HTTPS valide est obligatoire pour le rendu Browser.');
        }

        $parts = parse_url($value);
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower(rtrim((string) ($parts['host'] ?? ''), '.')) !== $domain
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('Le rendu Browser doit rester sur le domaine HTTPS autorisé.');
        }

        return $value;
    }
}
