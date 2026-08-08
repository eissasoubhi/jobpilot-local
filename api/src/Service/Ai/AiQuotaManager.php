<?php

declare(strict_types=1);

namespace App\Service\Ai;

final class AiQuotaManager
{
    private const DAY_TIMEZONE = 'America/Los_Angeles';

    private string $file;

    public function __construct(string $privateDir)
    {
        if (!is_dir($privateDir)) {
            @mkdir($privateDir, 0700, true);
        }

        $this->file = rtrim($privateDir, '/').'/ai-quota-usage.json';
    }

    /**
     * @param array{rpm: int, tpm: int, rpd: int, safetyPercent: int} $limits
     */
    public function reserve(string $provider, string $model, int $estimatedInputTokens, array $limits): ?string
    {
        $provider = strtolower(trim($provider));
        $model = trim($model);
        $estimatedInputTokens = max(1, $estimatedInputTokens);
        $normalizedLimits = $this->normalizeLimits($limits);
        $now = time();
        $reservationId = bin2hex(random_bytes(12));

        return $this->withLockedEvents(function (array &$events) use (
            $provider,
            $model,
            $estimatedInputTokens,
            $normalizedLimits,
            $now,
            $reservationId,
        ): ?string {
            $events = $this->prune($events, $now);
            $usage = $this->calculateUsage($events, $provider, $model, $now, $normalizedLimits);

            if (
                $usage['rpmUsed'] + 1 > $usage['rpmLimit']
                || $usage['tpmUsed'] + $estimatedInputTokens > $usage['tpmLimit']
                || $usage['rpdUsed'] + 1 > $usage['rpdLimit']
            ) {
                return null;
            }

            $events[] = [
                'id' => $reservationId,
                'provider' => $provider,
                'model' => $model,
                'at' => $now,
                'inputTokens' => $estimatedInputTokens,
            ];

            return $reservationId;
        });
    }

    public function reconcile(string $reservationId, int $actualInputTokens): void
    {
        $reservationId = trim($reservationId);
        if ($reservationId === '' || $actualInputTokens <= 0) {
            return;
        }

        $this->withLockedEvents(function (array &$events) use ($reservationId, $actualInputTokens): null {
            foreach ($events as &$event) {
                if (($event['id'] ?? null) === $reservationId) {
                    $event['inputTokens'] = max(1, $actualInputTokens);
                    break;
                }
            }
            unset($event);

            return null;
        });
    }

    /**
     * @param array{rpm: int, tpm: int, rpd: int, safetyPercent: int} $limits
     * @return array{rpmUsed: int, tpmUsed: int, rpdUsed: int, rpmLimit: int, tpmLimit: int, rpdLimit: int, providerRpm: int, providerTpm: int, providerRpd: int, safetyPercent: int, resetsAt: string, resetTimeZone: string}
     */
    public function status(string $provider, string $model, array $limits): array
    {
        $provider = strtolower(trim($provider));
        $model = trim($model);
        $normalizedLimits = $this->normalizeLimits($limits);
        $now = time();

        return $this->withLockedEvents(function (array &$events) use ($provider, $model, $normalizedLimits, $now): array {
            $events = $this->prune($events, $now);

            return $this->calculateUsage($events, $provider, $model, $now, $normalizedLimits);
        });
    }

    public function estimateTextInputTokens(string $text): int
    {
        // Deliberately conservative for mixed French/English/JSON prompts. We avoid
        // a separate countTokens request because it would consume another API call.
        return max(1, (int) ceil(strlen($text) / 3));
    }

    /**
     * @param list<array<string, mixed>> $events
     * @param array{rpm: int, tpm: int, rpd: int, safetyPercent: int} $limits
     * @return array{rpmUsed: int, tpmUsed: int, rpdUsed: int, rpmLimit: int, tpmLimit: int, rpdLimit: int, providerRpm: int, providerTpm: int, providerRpd: int, safetyPercent: int, resetsAt: string, resetTimeZone: string}
     */
    private function calculateUsage(array $events, string $provider, string $model, int $now, array $limits): array
    {
        $minuteCutoff = $now - 60;
        $dayStart = $this->dayStartTimestamp($now);
        $rpmUsed = 0;
        $tpmUsed = 0;
        $rpdUsed = 0;

        foreach ($events as $event) {
            if (($event['provider'] ?? null) !== $provider || ($event['model'] ?? null) !== $model) {
                continue;
            }

            $at = is_numeric($event['at'] ?? null) ? (int) $event['at'] : 0;
            $inputTokens = is_numeric($event['inputTokens'] ?? null) ? max(0, (int) $event['inputTokens']) : 0;

            if ($at >= $minuteCutoff) {
                ++$rpmUsed;
                $tpmUsed += $inputTokens;
            }
            if ($at >= $dayStart) {
                ++$rpdUsed;
            }
        }

        return [
            'rpmUsed' => $rpmUsed,
            'tpmUsed' => $tpmUsed,
            'rpdUsed' => $rpdUsed,
            'rpmLimit' => $this->guardedLimit($limits['rpm'], $limits['safetyPercent']),
            'tpmLimit' => $this->guardedLimit($limits['tpm'], $limits['safetyPercent']),
            'rpdLimit' => $this->guardedLimit($limits['rpd'], $limits['safetyPercent']),
            'providerRpm' => $limits['rpm'],
            'providerTpm' => $limits['tpm'],
            'providerRpd' => $limits['rpd'],
            'safetyPercent' => $limits['safetyPercent'],
            'resetsAt' => $this->nextDayReset($now)->format(DATE_ATOM),
            'resetTimeZone' => self::DAY_TIMEZONE,
        ];
    }

    /** @param list<array<string, mixed>> $events @return list<array<string, mixed>> */
    private function prune(array $events, int $now): array
    {
        $cutoff = min($now - 60, $this->dayStartTimestamp($now));

        return array_values(array_filter(
            $events,
            static fn(array $event): bool => is_numeric($event['at'] ?? null) && (int) $event['at'] >= $cutoff,
        ));
    }

    private function dayStartTimestamp(int $now): int
    {
        return (new \DateTimeImmutable('@'.$now))
            ->setTimezone(new \DateTimeZone(self::DAY_TIMEZONE))
            ->setTime(0, 0)
            ->getTimestamp();
    }

    private function nextDayReset(int $now): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@'.$now))
            ->setTimezone(new \DateTimeZone(self::DAY_TIMEZONE))
            ->modify('tomorrow')
            ->setTime(0, 0);
    }

    /** @param array{rpm: int, tpm: int, rpd: int, safetyPercent: int} $limits @return array{rpm: int, tpm: int, rpd: int, safetyPercent: int} */
    private function normalizeLimits(array $limits): array
    {
        return [
            'rpm' => max(1, (int) ($limits['rpm'] ?? 1)),
            'tpm' => max(1, (int) ($limits['tpm'] ?? 1)),
            'rpd' => max(1, (int) ($limits['rpd'] ?? 1)),
            'safetyPercent' => max(1, min(100, (int) ($limits['safetyPercent'] ?? 100))),
        ];
    }

    private function guardedLimit(int $providerLimit, int $safetyPercent): int
    {
        return max(1, (int) floor($providerLimit * ($safetyPercent / 100)));
    }

    /**
     * @template T
     * @param callable(list<array<string, mixed>>&): T $callback
     * @return T
     */
    private function withLockedEvents(callable $callback): mixed
    {
        $handle = fopen($this->file, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Impossible d’ouvrir le compteur de quotas IA.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Impossible de verrouiller le compteur de quotas IA.');
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $decoded = $raw !== false && trim($raw) !== '' ? json_decode($raw, true) : [];
            $events = is_array($decoded['events'] ?? null) ? array_values($decoded['events']) : [];

            $result = $callback($events);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode(['events' => $events], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            fflush($handle);
            chmod($this->file, 0600);
            flock($handle, LOCK_UN);

            return $result;
        } finally {
            fclose($handle);
        }
    }
}
