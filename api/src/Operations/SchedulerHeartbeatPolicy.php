<?php

declare(strict_types=1);

namespace App\Operations;

final class SchedulerHeartbeatPolicy
{
    public const DEFAULT_INTERVAL_SECONDS = 21_600;
    public const DEFAULT_GRACE_SECONDS = 300;

    public static function maxAgeSeconds(int $intervalSeconds, int $graceSeconds): int
    {
        if ($intervalSeconds < 1) {
            throw new \InvalidArgumentException('Scheduler interval must be at least one second.');
        }

        if ($graceSeconds < 0) {
            throw new \InvalidArgumentException('Scheduler heartbeat grace period cannot be negative.');
        }

        return $intervalSeconds + $graceSeconds;
    }

    public static function isFresh(
        int $heartbeatTimestamp,
        int $nowTimestamp,
        int $intervalSeconds,
        int $graceSeconds,
    ): bool {
        if ($heartbeatTimestamp < 1 || $nowTimestamp < 1 || $heartbeatTimestamp > $nowTimestamp) {
            return false;
        }

        return ($nowTimestamp - $heartbeatTimestamp) <= self::maxAgeSeconds($intervalSeconds, $graceSeconds);
    }
}
