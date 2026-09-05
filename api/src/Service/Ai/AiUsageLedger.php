<?php

declare(strict_types=1);

namespace App\Service\Ai;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AiUsageLedger
{
    private const RETENTION_DAYS = 400;
    private const MAX_EVENTS = 50_000;
    private const TIMEZONE = 'Europe/Paris';

    private string $file;

    public function __construct(
        #[Autowire('%private_dir%')] string $privateDir,
        private readonly AiUsagePricing $pricing,
        private readonly AiUsagePreferencesStore $preferences,
    ) {
        if (!is_dir($privateDir)) {
            @mkdir($privateDir, 0700, true);
        }

        $this->file = rtrim($privateDir, '/').'/ai-usage-events.json';
    }

    /**
     * @param array<string, mixed> $usage
     */
    public function record(
        string $provider,
        string $model,
        string $purpose,
        string $outcome,
        array $usage = [],
        ?int $latencyMs = null,
        ?string $entityType = null,
        string|int|null $entityId = null,
        ?int $httpStatus = null,
        ?string $errorClass = null,
    ): void {
        $provider = $this->label($provider, 40, 'unknown');
        $model = $this->label($model, 160, 'unknown');
        $purpose = $this->label($purpose, 80, 'unknown');
        $outcome = $this->label($outcome, 80, 'unknown');
        $normalizedUsage = $this->normalizeUsage($usage);
        $billingTier = $this->preferences->get()['billingTier'];
        $cost = $this->pricing->estimate($provider, $model, $normalizedUsage, $billingTier);
        $now = time();

        $event = [
            'id' => bin2hex(random_bytes(12)),
            'at' => $now,
            'provider' => $provider,
            'model' => $model,
            'purpose' => $purpose,
            'outcome' => $outcome,
            'providerCall' => in_array($outcome, ['provider_success', 'provider_failure'], true),
            'cacheHit' => $outcome === 'cache_hit',
            'quotaBlocked' => $outcome === 'quota_blocked',
            'inputTokens' => $normalizedUsage['total_input_tokens'],
            'outputTokens' => $normalizedUsage['total_output_tokens'],
            'thoughtTokens' => $normalizedUsage['total_thought_tokens'],
            'cachedTokens' => $normalizedUsage['total_cached_tokens'],
            'toolUseTokens' => $normalizedUsage['total_tool_use_tokens'],
            'totalTokens' => $normalizedUsage['total_tokens'],
            'latencyMs' => $latencyMs !== null ? max(0, $latencyMs) : null,
            'entityType' => $entityType !== null ? $this->label($entityType, 80, '') : null,
            'entityId' => $entityId !== null ? mb_substr((string) $entityId, 0, 160) : null,
            'httpStatus' => $httpStatus !== null ? max(0, $httpStatus) : null,
            'errorClass' => $errorClass !== null ? mb_substr($errorClass, 0, 240) : null,
            'estimatedCostUsd' => $cost['estimatedCostUsd'],
            'pricingVersion' => $cost['version'],
            'pricingSupported' => $cost['supported'],
        ];

        $this->withLockedEvents(function (array &$events) use ($event, $now): void {
            $events = $this->prune($events, $now);
            $events[] = $event;
            if (count($events) > self::MAX_EVENTS) {
                $events = array_slice($events, -self::MAX_EVENTS);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(
        ?string $selectedDate = null,
        ?float $usdToEurRate = null,
        ?float $prepaidCreditUsd = null,
        ?int $prepaidCreditSetAt = null,
    ): array {
        $timezone = new \DateTimeZone(self::TIMEZONE);
        $now = new \DateTimeImmutable('now', $timezone);
        $events = $this->readEvents();
        $events = $this->prune($events, $now->getTimestamp());

        $todayStart = $now->setTime(0, 0);
        $sevenDaysStart = $todayStart->modify('-6 days');
        $monthStart = $todayStart->modify('first day of this month');
        $yearStart = $todayStart->setDate((int) $now->format('Y'), 1, 1);

        $summaries = [
            'today' => $this->summarySince($events, $todayStart->getTimestamp(), $usdToEurRate),
            'sevenDays' => $this->summarySince($events, $sevenDaysStart->getTimestamp(), $usdToEurRate),
            'month' => $this->summarySince($events, $monthStart->getTimestamp(), $usdToEurRate),
            'year' => $this->summarySince($events, $yearStart->getTimestamp(), $usdToEurRate),
        ];

        $calendarStart = $todayStart->modify('-364 days')->getTimestamp();
        $calendar = [];
        $purposes = [];
        $models = [];
        foreach ($events as $event) {
            $at = (int) ($event['at'] ?? 0);
            if ($at < $calendarStart) {
                continue;
            }
            $date = (new \DateTimeImmutable('@'.$at))->setTimezone($timezone)->format('Y-m-d');
            if (!isset($calendar[$date])) {
                $calendar[$date] = $this->emptySummary();
                $calendar[$date]['date'] = $date;
            }
            $this->addEventToSummary($calendar[$date], $event);

            $purpose = (string) ($event['purpose'] ?? 'unknown');
            if (!isset($purposes[$purpose])) {
                $purposes[$purpose] = $this->emptySummary();
                $purposes[$purpose]['purpose'] = $purpose;
            }
            $this->addEventToSummary($purposes[$purpose], $event);

            $model = (string) ($event['model'] ?? 'unknown');
            if (!isset($models[$model])) {
                $models[$model] = $this->emptySummary();
                $models[$model]['model'] = $model;
            }
            $this->addEventToSummary($models[$model], $event);
        }

        ksort($calendar);
        foreach ($calendar as &$day) {
            $this->finalizeSummary($day, $usdToEurRate);
        }
        unset($day);
        foreach ($purposes as &$purposeSummary) {
            $this->finalizeSummary($purposeSummary, $usdToEurRate);
        }
        unset($purposeSummary);
        foreach ($models as &$modelSummary) {
            $this->finalizeSummary($modelSummary, $usdToEurRate);
        }
        unset($modelSummary);

        uasort($purposes, static fn (array $left, array $right): int => $right['operations'] <=> $left['operations']);
        uasort($models, static fn (array $left, array $right): int => $right['providerCalls'] <=> $left['providerCalls']);

        $selectedEvents = $this->selectedEvents($events, $selectedDate, $timezone);
        $trackedCostSinceBaseline = null;
        $estimatedRemainingUsd = null;
        if ($prepaidCreditUsd !== null) {
            $trackedCostSinceBaseline = 0.0;
            foreach ($events as $event) {
                if ($prepaidCreditSetAt !== null && (int) ($event['at'] ?? 0) < $prepaidCreditSetAt) {
                    continue;
                }
                if (is_numeric($event['estimatedCostUsd'] ?? null)) {
                    $trackedCostSinceBaseline += (float) $event['estimatedCostUsd'];
                }
            }
            $trackedCostSinceBaseline = round($trackedCostSinceBaseline, 10);
            $estimatedRemainingUsd = max(0.0, round($prepaidCreditUsd - $trackedCostSinceBaseline, 10));
        }

        return [
            'timezone' => self::TIMEZONE,
            'selectedDate' => $selectedDate,
            'summaries' => $summaries,
            'calendar' => array_values($calendar),
            'purposes' => array_values($purposes),
            'models' => array_values($models),
            'events' => $selectedEvents,
            'credit' => [
                'baselineUsd' => $prepaidCreditUsd,
                'baselineAt' => $prepaidCreditSetAt !== null ? date(DATE_ATOM, $prepaidCreditSetAt) : null,
                'trackedCostSinceBaselineUsd' => $trackedCostSinceBaseline,
                'estimatedRemainingUsd' => $estimatedRemainingUsd,
                'estimatedRemainingEur' => $estimatedRemainingUsd !== null && $usdToEurRate !== null
                    ? round($estimatedRemainingUsd * $usdToEurRate, 8)
                    : null,
                'label' => 'local_estimate',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function selectedEvents(array $events, ?string $selectedDate, \DateTimeZone $timezone): array
    {
        $filtered = [];
        foreach ($events as $event) {
            $at = (int) ($event['at'] ?? 0);
            $eventDate = (new \DateTimeImmutable('@'.$at))->setTimezone($timezone)->format('Y-m-d');
            if ($selectedDate !== null && $eventDate !== $selectedDate) {
                continue;
            }
            $event['atIso'] = (new \DateTimeImmutable('@'.$at))->setTimezone($timezone)->format(DATE_ATOM);
            $filtered[] = $event;
        }

        usort($filtered, static fn (array $left, array $right): int => ((int) $right['at']) <=> ((int) $left['at']));

        return array_slice($filtered, 0, $selectedDate !== null ? 500 : 100);
    }

    /** @return array<string, mixed> */
    private function summarySince(array $events, int $since, ?float $usdToEurRate): array
    {
        $summary = $this->emptySummary();
        foreach ($events as $event) {
            if ((int) ($event['at'] ?? 0) < $since) {
                continue;
            }
            $this->addEventToSummary($summary, $event);
        }
        $this->finalizeSummary($summary, $usdToEurRate);

        return $summary;
    }

    /** @return array<string, mixed> */
    private function emptySummary(): array
    {
        return [
            'operations' => 0,
            'providerCalls' => 0,
            'successfulProviderCalls' => 0,
            'failedProviderCalls' => 0,
            'cacheHits' => 0,
            'quotaBlocked' => 0,
            'inputTokens' => 0,
            'outputTokens' => 0,
            'thoughtTokens' => 0,
            'cachedTokens' => 0,
            'toolUseTokens' => 0,
            'totalTokens' => 0,
            'estimatedCostUsd' => 0.0,
            'estimatedCostEur' => null,
            'pricedCalls' => 0,
            'unpricedCalls' => 0,
            'latencyTotalMs' => 0,
            'latencySamples' => 0,
            'averageLatencyMs' => null,
            'cacheHitRate' => 0.0,
        ];
    }

    /** @param array<string, mixed> $summary @param array<string, mixed> $event */
    private function addEventToSummary(array &$summary, array $event): void
    {
        ++$summary['operations'];
        if (($event['providerCall'] ?? false) === true) {
            ++$summary['providerCalls'];
            if (($event['outcome'] ?? null) === 'provider_success') {
                ++$summary['successfulProviderCalls'];
            } else {
                ++$summary['failedProviderCalls'];
            }
        }
        if (($event['cacheHit'] ?? false) === true) {
            ++$summary['cacheHits'];
        }
        if (($event['quotaBlocked'] ?? false) === true) {
            ++$summary['quotaBlocked'];
        }

        foreach ([
            'inputTokens', 'outputTokens', 'thoughtTokens', 'cachedTokens', 'toolUseTokens', 'totalTokens',
        ] as $field) {
            $summary[$field] += is_numeric($event[$field] ?? null) ? max(0, (int) $event[$field]) : 0;
        }

        if (is_numeric($event['estimatedCostUsd'] ?? null)) {
            $summary['estimatedCostUsd'] += (float) $event['estimatedCostUsd'];
            if (($event['providerCall'] ?? false) === true) {
                ++$summary['pricedCalls'];
            }
        } elseif (($event['providerCall'] ?? false) === true) {
            ++$summary['unpricedCalls'];
        }

        if (is_numeric($event['latencyMs'] ?? null)) {
            $summary['latencyTotalMs'] += max(0, (int) $event['latencyMs']);
            ++$summary['latencySamples'];
        }
    }

    /** @param array<string, mixed> $summary */
    private function finalizeSummary(array &$summary, ?float $usdToEurRate): void
    {
        $summary['estimatedCostUsd'] = round((float) $summary['estimatedCostUsd'], 10);
        $summary['estimatedCostEur'] = $usdToEurRate !== null
            ? round($summary['estimatedCostUsd'] * $usdToEurRate, 8)
            : null;
        $summary['averageLatencyMs'] = $summary['latencySamples'] > 0
            ? (int) round($summary['latencyTotalMs'] / $summary['latencySamples'])
            : null;
        $eligibleForCache = $summary['providerCalls'] + $summary['cacheHits'];
        $summary['cacheHitRate'] = $eligibleForCache > 0
            ? round(($summary['cacheHits'] / $eligibleForCache) * 100, 1)
            : 0.0;
        unset($summary['latencyTotalMs'], $summary['latencySamples']);
    }

    /** @param array<string, mixed> $usage @return array<string, int> */
    private function normalizeUsage(array $usage): array
    {
        $normalized = [];
        foreach ([
            'total_input_tokens',
            'total_output_tokens',
            'total_thought_tokens',
            'total_cached_tokens',
            'total_tool_use_tokens',
            'total_tokens',
        ] as $field) {
            $normalized[$field] = is_numeric($usage[$field] ?? null) ? max(0, (int) $usage[$field]) : 0;
        }

        if ($normalized['total_tokens'] === 0) {
            $normalized['total_tokens'] = $normalized['total_input_tokens']
                + $normalized['total_output_tokens']
                + $normalized['total_thought_tokens']
                + $normalized['total_tool_use_tokens'];
        }

        return $normalized;
    }

    private function label(string $value, int $length, string $fallback): string
    {
        $value = trim($value);

        return $value === '' ? $fallback : mb_substr($value, 0, $length);
    }

    /** @return list<array<string, mixed>> */
    private function readEvents(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $raw = file_get_contents($this->file);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded['events'] ?? null) ? array_values(array_filter($decoded['events'], 'is_array')) : [];
    }

    /** @param list<array<string, mixed>> $events @return list<array<string, mixed>> */
    private function prune(array $events, int $now): array
    {
        $cutoff = $now - (self::RETENTION_DAYS * 86400);

        return array_values(array_filter(
            $events,
            static fn (array $event): bool => is_numeric($event['at'] ?? null) && (int) $event['at'] >= $cutoff,
        ));
    }

    /**
     * @param callable(list<array<string, mixed>>&): void $callback
     */
    private function withLockedEvents(callable $callback): void
    {
        $handle = fopen($this->file, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Impossible d’ouvrir le journal d’utilisation IA.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Impossible de verrouiller le journal d’utilisation IA.');
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $events = [];
            if ($raw !== false && trim($raw) !== '') {
                try {
                    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    $events = is_array($decoded['events'] ?? null)
                        ? array_values(array_filter($decoded['events'], 'is_array'))
                        : [];
                } catch (\JsonException) {
                    $events = [];
                }
            }

            $callback($events);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode(
                ['events' => $events],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
            fflush($handle);
            chmod($this->file, 0600);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }
}
