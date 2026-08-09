<?php

declare(strict_types=1);

namespace App\Service\Ai;

final class CustomScraperAiExtractionCache
{
    private const TTL_SECONDS = 2_592_000; // 30 days
    private const MAX_ENTRIES = 1_000;

    private string $file;

    public function __construct(string $privateDir)
    {
        if (!is_dir($privateDir)) {
            @mkdir($privateDir, 0700, true);
        }

        $this->file = rtrim($privateDir, '/').'/ai-scraper-extraction-cache.json';
    }

    /** @return array<string, mixed>|null */
    public function get(string $provider, string $model, string $fingerprint): ?array
    {
        $key = $this->key($provider, $model, $fingerprint);
        $now = time();

        return $this->withLockedEntries(function (array &$entries) use ($key, $now): ?array {
            $entries = $this->pruneExpired($entries, $now);
            $entry = $entries[$key] ?? null;
            if (!is_array($entry) || !is_array($entry['result'] ?? null)) {
                return null;
            }

            return $entry['result'];
        });
    }

    /** @param array<string, mixed> $result */
    public function put(string $provider, string $model, string $fingerprint, array $result): void
    {
        $key = $this->key($provider, $model, $fingerprint);
        $now = time();

        $this->withLockedEntries(function (array &$entries) use ($key, $now, $result): null {
            $entries = $this->pruneExpired($entries, $now);
            $entries[$key] = [
                'createdAt' => $now,
                'result' => $result,
            ];

            if (count($entries) > self::MAX_ENTRIES) {
                uasort(
                    $entries,
                    static fn (array $left, array $right): int => ((int) ($right['createdAt'] ?? 0)) <=> ((int) ($left['createdAt'] ?? 0)),
                );
                $entries = array_slice($entries, 0, self::MAX_ENTRIES, true);
            }

            return null;
        });
    }

    private function key(string $provider, string $model, string $fingerprint): string
    {
        return hash('sha256', implode("\0", [
            strtolower(trim($provider)),
            trim($model),
            trim($fingerprint),
        ]));
    }

    /**
     * @param array<string, array<string, mixed>> $entries
     * @return array<string, array<string, mixed>>
     */
    private function pruneExpired(array $entries, int $now): array
    {
        $cutoff = $now - self::TTL_SECONDS;
        foreach ($entries as $key => $entry) {
            if (!is_array($entry) || !is_numeric($entry['createdAt'] ?? null) || (int) $entry['createdAt'] < $cutoff) {
                unset($entries[$key]);
            }
        }

        return $entries;
    }

    /**
     * @template T
     * @param callable(array<string, array<string, mixed>>&): T $callback
     * @return T
     */
    private function withLockedEntries(callable $callback): mixed
    {
        $handle = fopen($this->file, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Impossible d’ouvrir le cache IA des scrapers.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Impossible de verrouiller le cache IA des scrapers.');
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $decoded = $raw !== false && trim($raw) !== '' ? json_decode($raw, true) : [];
            $entries = is_array($decoded['entries'] ?? null) ? $decoded['entries'] : [];

            $result = $callback($entries);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode(
                ['entries' => $entries],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
            fflush($handle);
            chmod($this->file, 0600);
            flock($handle, LOCK_UN);

            return $result;
        } finally {
            fclose($handle);
        }
    }
}
