<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Http;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RobotsTxtGuard
{
    private const CACHE_TTL_SECONDS = 86_400;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $cacheDirectory,
    ) {
    }

    public function assertAllowed(string $url, string $userAgent): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';
        if ($scheme === '' || $host === '') {
            throw new HttpScrapingException('Impossible de déterminer l’origine pour robots.txt.');
        }

        $origin = $scheme.'://'.$host.$port;
        $robots = $this->load($origin, $userAgent);
        if (!$this->isAllowed($robots, $url, $userAgent)) {
            throw new HttpScrapingException(sprintf('robots.txt interdit la collecte de %s.', $url));
        }
    }

    private function load(string $origin, string $userAgent): string
    {
        $cached = $this->readCache($origin);
        if ($cached !== null) {
            return $cached;
        }

        $response = $this->httpClient->request('GET', $origin.'/robots.txt', [
            'headers' => [
                'Accept' => 'text/plain,*/*;q=0.1',
                'User-Agent' => $userAgent,
            ],
            'timeout' => 5,
            'max_redirects' => 0,
        ]);
        $statusCode = $response->getStatusCode();

        if (in_array($statusCode, [404, 410], true)) {
            $this->writeCache($origin, '');
            return '';
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new HttpScrapingException(sprintf('robots.txt est indisponible avec le statut HTTP %d.', $statusCode));
        }

        $body = $response->getContent(false);
        if (strlen($body) > 512_000) {
            throw new HttpScrapingException('robots.txt dépasse la taille maximale autorisée.');
        }

        $this->writeCache($origin, $body);

        return $body;
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

    private function readCache(string $origin): ?string
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

        return is_array($payload) && is_string($payload['body'] ?? null) ? $payload['body'] : null;
    }

    private function writeCache(string $origin, string $body): void
    {
        if (!is_dir($this->cacheDirectory)
            && !mkdir($concurrentDirectory = $this->cacheDirectory, 0700, true)
            && !is_dir($concurrentDirectory)) {
            throw new HttpScrapingException('Impossible de créer le cache robots.txt.');
        }

        $payload = json_encode(['origin' => $origin, 'body' => $body], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($this->cachePath($origin), $payload, LOCK_EX) === false) {
            throw new HttpScrapingException('Impossible d’écrire le cache robots.txt.');
        }
    }

    private function cachePath(string $origin): string
    {
        return rtrim($this->cacheDirectory, '/').'/'.hash('sha256', $origin).'.json';
    }
}
