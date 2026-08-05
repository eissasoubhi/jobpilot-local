<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

final class ConnectorFreshnessAnalyzer
{
    /**
     * @return array{
     *   status: string,
     *   label: string,
     *   alert: bool,
     *   lastSyncedAt: string|null,
     *   nextExpectedAt: string|null,
     *   overdueBySeconds: int,
     *   reason: string
     * }
     */
    public function analyze(
        ?\DateTimeImmutable $lastSyncedAt,
        bool $canSynchronize,
        int $intervalSeconds,
        ?\DateTimeImmutable $now = null,
    ): array {
        $intervalSeconds = max(900, $intervalSeconds);
        $now ??= new \DateTimeImmutable();

        if (!$canSynchronize) {
            return $this->result(
                'INACTIVE',
                'Inactive',
                false,
                $lastSyncedAt,
                null,
                0,
                'The connector is disabled, incomplete, or blocked by its collection policy.',
            );
        }

        if ($lastSyncedAt === null) {
            return $this->result(
                'NEVER_SYNCED',
                'Never synchronized',
                true,
                null,
                null,
                0,
                'The connector is active but has never completed a synchronization.',
            );
        }

        $nextExpectedAt = $lastSyncedAt->modify(sprintf('+%d seconds', $intervalSeconds));
        $overdueBySeconds = max(0, $now->getTimestamp() - $nextExpectedAt->getTimestamp());

        if ($overdueBySeconds === 0) {
            return $this->result(
                'FRESH',
                'Fresh',
                false,
                $lastSyncedAt,
                $nextExpectedAt,
                0,
                'The last synchronization is still within the expected interval.',
            );
        }

        if ($overdueBySeconds <= $intervalSeconds) {
            return $this->result(
                'DUE',
                'Due',
                false,
                $lastSyncedAt,
                $nextExpectedAt,
                $overdueBySeconds,
                'The connector is due and should run during the next scheduler pass.',
            );
        }

        if ($overdueBySeconds <= 2 * $intervalSeconds) {
            return $this->result(
                'OVERDUE',
                'Overdue',
                true,
                $lastSyncedAt,
                $nextExpectedAt,
                $overdueBySeconds,
                'The connector missed at least one complete synchronization interval.',
            );
        }

        return $this->result(
            'STALE',
            'Stale',
            true,
            $lastSyncedAt,
            $nextExpectedAt,
            $overdueBySeconds,
            'The connector has missed several synchronization intervals; check the scheduler and connector state.',
        );
    }

    /** @return array<string, mixed> */
    private function result(
        string $status,
        string $label,
        bool $alert,
        ?\DateTimeImmutable $lastSyncedAt,
        ?\DateTimeImmutable $nextExpectedAt,
        int $overdueBySeconds,
        string $reason,
    ): array {
        return [
            'status' => $status,
            'label' => $label,
            'alert' => $alert,
            'lastSyncedAt' => $lastSyncedAt?->format(DATE_ATOM),
            'nextExpectedAt' => $nextExpectedAt?->format(DATE_ATOM),
            'overdueBySeconds' => $overdueBySeconds,
            'reason' => $reason,
        ];
    }
}
