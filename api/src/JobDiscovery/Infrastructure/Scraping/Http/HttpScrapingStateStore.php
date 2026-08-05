<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Http;

final class HttpScrapingStateStore
{
    public function __construct(private string $directory)
    {
    }

    /** @return array<string, mixed> */
    public function read(string $connectorCode): array
    {
        $path = $this->path($connectorCode);
        $state = [];

        if (is_file($path)) {
            $content = file_get_contents($path);
            if (is_string($content) && trim($content) !== '') {
                try {
                    $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                    $state = is_array($decoded) ? $decoded : [];
                } catch (\JsonException) {
                    $state = [];
                }
            }
        }

        $today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        if (($state['quotaDay'] ?? null) !== $today) {
            $state['quotaDay'] = $today;
            $state['requestsToday'] = 0;
        }

        return [
            'quotaDay' => $state['quotaDay'],
            'requestsToday' => max(0, (int) ($state['requestsToday'] ?? 0)),
            'lastRequestAt' => is_numeric($state['lastRequestAt'] ?? null) ? (float) $state['lastRequestAt'] : null,
            'consecutiveFailures' => max(0, (int) ($state['consecutiveFailures'] ?? 0)),
            'circuitOpenUntil' => isset($state['circuitOpenUntil']) && is_string($state['circuitOpenUntil'])
                ? $state['circuitOpenUntil']
                : null,
            'cache' => is_array($state['cache'] ?? null) ? $state['cache'] : [],
        ];
    }

    /** @param array<string, mixed> $state */
    public function write(string $connectorCode, array $state): void
    {
        $this->ensureDirectory();
        $path = $this->path($connectorCode);
        $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));
        $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new HttpScrapingException('Impossible de persister l’état du scraper HTTP.');
        }
        @chmod($temporary, 0600);

        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new HttpScrapingException('Impossible de remplacer l’état du scraper HTTP.');
        }
    }

    private function path(string $connectorCode): string
    {
        $normalized = strtolower(trim($connectorCode));
        $safe = preg_replace('/[^a-z0-9_-]+/', '-', $normalized) ?: 'connector';

        return rtrim($this->directory, '/').'/'.$safe.'.json';
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!mkdir($concurrentDirectory = $this->directory, 0700, true) && !is_dir($concurrentDirectory)) {
            throw new HttpScrapingException('Impossible de créer le stockage privé du scraper HTTP.');
        }
    }
}
