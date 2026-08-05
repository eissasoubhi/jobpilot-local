<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Application\ConnectorFreshnessReportFormatter;
use PHPUnit\Framework\TestCase;

final class ConnectorFreshnessReportFormatterTest extends TestCase
{
    public function testHealthyReportIsMachineReadable(): void
    {
        $json = (new ConnectorFreshnessReportFormatter())->toJson([
            [
                'code' => 'arbeitnow',
                'name' => 'Arbeitnow',
                'status' => 'FRESH',
                'alert' => false,
                'lastSyncedAt' => '2026-08-05T18:00:00+02:00',
                'nextExpectedAt' => '2026-08-06T00:00:00+02:00',
                'overdueBySeconds' => 0,
                'reason' => 'Fresh.',
            ],
        ], 21600);

        $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('OK', $report['status']);
        self::assertSame(0, $report['alertCount']);
        self::assertSame(1, $report['connectorCount']);
        self::assertSame(21600, $report['intervalSeconds']);
        self::assertSame('arbeitnow', $report['connectors'][0]['code']);
        self::assertArrayHasKey('generatedAt', $report);
    }

    public function testAlertReportCountsOnlyAlertingConnectorsAndClampsInterval(): void
    {
        $json = (new ConnectorFreshnessReportFormatter())->toJson([
            ['code' => 'symfony-jobs', 'status' => 'STALE', 'alert' => true],
            ['code' => 'gmail', 'status' => 'INACTIVE', 'alert' => false],
        ], 1);

        $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('ALERT', $report['status']);
        self::assertSame(1, $report['alertCount']);
        self::assertSame(2, $report['connectorCount']);
        self::assertSame(900, $report['intervalSeconds']);
    }
}
