<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Application\ConnectorHealthAnalyzer;
use PHPUnit\Framework\TestCase;

final class ConnectorHealthAnalyzerTest extends TestCase
{
    private ConnectorHealthAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new ConnectorHealthAnalyzer();
    }

    public function testNoCompletedRunHasNoReference(): void
    {
        $health = $this->analyzer->analyze([]);

        self::assertSame('NO_DATA', $health['status']);
        self::assertFalse($health['alert']);
        self::assertSame(0, $health['sampleSize']);
    }

    public function testHealthyRunReportsExtractionRate(): void
    {
        $health = $this->analyzer->analyze([
            $this->run('SUCCEEDED', 10, 0),
            $this->run('SUCCEEDED', 8, 0),
        ]);

        self::assertSame('HEALTHY', $health['status']);
        self::assertSame(100.0, $health['lastExtractionRate']);
        self::assertSame(9.0, $health['baselineAverageReceived']);
        self::assertFalse($health['alert']);
    }

    public function testTwoZeroRunsAfterPositiveBaselineAreDegraded(): void
    {
        $health = $this->analyzer->analyze([
            $this->run('SUCCEEDED', 0, 0),
            $this->run('SUCCEEDED', 0, 0),
            $this->run('SUCCEEDED', 12, 0),
        ]);

        self::assertSame('DEGRADED', $health['status']);
        self::assertSame(2, $health['consecutiveZeroRuns']);
        self::assertTrue($health['alert']);
    }

    public function testThreeZeroRunsAfterPositiveBaselineAreBroken(): void
    {
        $health = $this->analyzer->analyze([
            $this->run('SUCCEEDED', 0, 0),
            $this->run('SUCCEEDED', 0, 0),
            $this->run('SUCCEEDED', 0, 0),
            $this->run('SUCCEEDED', 15, 0),
        ]);

        self::assertSame('BROKEN', $health['status']);
        self::assertSame(3, $health['consecutiveZeroRuns']);
        self::assertTrue($health['alert']);
    }

    public function testHighNormalizationFailureRateIsBroken(): void
    {
        $health = $this->analyzer->analyze([
            $this->run('PARTIAL', 10, 6),
            $this->run('SUCCEEDED', 10, 0),
        ]);

        self::assertSame('BROKEN', $health['status']);
        self::assertSame(40.0, $health['lastExtractionRate']);
    }

    public function testFailedRunIsBrokenEvenWithoutReceivedItems(): void
    {
        $health = $this->analyzer->analyze([
            $this->run('FAILED', 0, 1, 'Flux XML invalide.'),
        ]);

        self::assertSame('BROKEN', $health['status']);
        self::assertStringContainsString('Flux XML invalide', $health['reasons'][0]);
    }

    /** @return array<string, mixed> */
    private function run(string $status, int $received, int $failed, ?string $error = null): array
    {
        return [
            'status' => $status,
            'received' => $received,
            'failed' => $failed,
            'error' => $error,
        ];
    }
}
