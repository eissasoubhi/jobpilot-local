<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Application\ConnectorFreshnessAnalyzer;
use PHPUnit\Framework\TestCase;

final class ConnectorFreshnessAnalyzerTest extends TestCase
{
    private ConnectorFreshnessAnalyzer $analyzer;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->analyzer = new ConnectorFreshnessAnalyzer();
        $this->now = new \DateTimeImmutable('2026-08-05T18:00:00+02:00');
    }

    public function testInactiveConnectorNeverRaisesFreshnessAlert(): void
    {
        $result = $this->analyzer->analyze(null, false, 3600, $this->now);

        self::assertSame('INACTIVE', $result['status']);
        self::assertFalse($result['alert']);
    }

    public function testActiveConnectorThatNeverRanRaisesAlert(): void
    {
        $result = $this->analyzer->analyze(null, true, 3600, $this->now);

        self::assertSame('NEVER_SYNCED', $result['status']);
        self::assertTrue($result['alert']);
    }

    public function testRecentSynchronizationIsFresh(): void
    {
        $result = $this->analyzer->analyze(
            new \DateTimeImmutable('2026-08-05T17:30:00+02:00'),
            true,
            3600,
            $this->now,
        );

        self::assertSame('FRESH', $result['status']);
        self::assertSame(0, $result['overdueBySeconds']);
        self::assertFalse($result['alert']);
    }

    public function testSingleLateIntervalIsDueWithoutAlert(): void
    {
        $result = $this->analyzer->analyze(
            new \DateTimeImmutable('2026-08-05T16:30:00+02:00'),
            true,
            3600,
            $this->now,
        );

        self::assertSame('DUE', $result['status']);
        self::assertSame(1800, $result['overdueBySeconds']);
        self::assertFalse($result['alert']);
    }

    public function testMissedFullIntervalIsOverdue(): void
    {
        $result = $this->analyzer->analyze(
            new \DateTimeImmutable('2026-08-05T15:30:00+02:00'),
            true,
            3600,
            $this->now,
        );

        self::assertSame('OVERDUE', $result['status']);
        self::assertSame(5400, $result['overdueBySeconds']);
        self::assertTrue($result['alert']);
    }

    public function testSeveralMissedIntervalsAreStale(): void
    {
        $result = $this->analyzer->analyze(
            new \DateTimeImmutable('2026-08-05T13:00:00+02:00'),
            true,
            3600,
            $this->now,
        );

        self::assertSame('STALE', $result['status']);
        self::assertSame(14400, $result['overdueBySeconds']);
        self::assertTrue($result['alert']);
    }

    public function testIntervalIsNeverLowerThanFifteenMinutes(): void
    {
        $result = $this->analyzer->analyze(
            new \DateTimeImmutable('2026-08-05T17:50:00+02:00'),
            true,
            1,
            $this->now,
        );

        self::assertSame('FRESH', $result['status']);
        self::assertSame('2026-08-05T18:05:00+02:00', $result['nextExpectedAt']);
    }
}
