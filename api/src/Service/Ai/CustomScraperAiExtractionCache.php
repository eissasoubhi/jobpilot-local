<?php

declare(strict_types=1);

namespace App\Service\Ai;

final class CustomScraperAiExtractionCache
{
    private const CACHE_VERSION = 1;
    private const TTL_SECONDS = 604_800;
    private const MAX_ENTRIES = 1_000;

    private string $file;

    public function __construct(string $privateDir)
    {
        $this->file = rtrim($privateDir, '/').'/ai-custom-scraper-extraction-cache.json';
    }

    /** @return list<array<string, mixed>>|null */
    public function get(string $provider, string $model, string $fingerprint): ?array
    {
        $state = $this->readState();
        $key = $this->key($provider, $model, $fingerprint);
        $entry = $state['entries'][$key] ?? null;
        if (!is_array($entry)) {
            return null;
        }

        $expiresAt = strtotime((string) ($entry['expiresAt'] ?? ''));
        if ($expiresAt === false || $expiresAt < time()) {
            return null;
        }

        $offers = $entry['offers'] ?? null;
        if (!is_array($offers)) {
            return null;
        }

        return array_values(array_filter($offers, 'is_array'));
    }

    /** @param list<array<string, mixed>> $offers */
    public function put(string $provider, string $model, string $fingerprint, array $offers): void
    {
        $directory = dirname($this->file);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le dossier privé du cache IA de scraping.');
        }

        $handle = fopen($this->file, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Impossible d’ouvrir le cache IA de scraping.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Impossible de verrouiller le cache IA de scraping.');
            }

            $contents = stream_get_contents($handle);
            $state = $this->decodeState(is_string($contents) ? $contents : '');
            $now = time();
            foreach ($state['entries'] as $key => $entry) {
                $expiresAt = strtotime((string) ($entry['expiresAt'] ?? ''));
                if ($expiresAt === false || $expiresAt < $now) {
                    unset($state['entries'][$key]);
                }
            }

            $state['entries'][$this->key($provider, $model, $fingerprint)] = [
                'createdAt' => gmdate(DATE_ATOM, $now),
                'expiresAt' => gmdate(DATE_ATOM, $now + self::TTL_SECONDS),
                'offers' => array_values($offers),
            ];

            if (count($state['entries']) > self::MAX_ENTRIES) {
                uasort($state['entries'], static fn (array $a, array $b): int => strcmp(
                    (string) ($a['createdAt'] ?? ''),
                    (string) ($b['createdAt'] ?? ''),
                ));
                $state['entries'] = array_slice($state['entries'], -self::MAX_ENTRIES, null, true);
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
            @chmod($this->file, 0600);
        }
    }

    /** @return array{version: int, entries: array<string, array<string, mixed>>} */
    private function readState(): array
    {
        if (!is_file($this->file)) {
            return $this->emptyState();
        }

        return $this->decodeState((string) file_get_contents($this->file));
    }

    /** @return array{version: int, entries: array<string, array<string, mixed>>} */
    private function decodeState(string $contents): array
    {
        if (trim($contents) === '') {
            return $this->emptyState();
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->emptyState();
        }

        if (!is_array($decoded) || (int) ($decoded['version'] ?? 0) !== self::CACHE_VERSION || !is_array($decoded['entries'] ?? null)) {
            return $this->emptyState();
        }

        return [
            'version' => self::CACHE_VERSION,
            'entries' => $decoded['entries'],
        ];
    }

    /** @return array{version: int, entries: array<string, array<string, mixed>>} */
    private function emptyState(): array
    {
        return ['version' => self::CACHE_VERSION, 'entries' => []];
    }

    private function key(string $provider, string $model, string $fingerprint): string
    {
        return hash('sha256', strtolower(trim($provider)).'|'.trim($model).'|'.$fingerprint);
    }
}
