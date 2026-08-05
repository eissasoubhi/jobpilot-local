<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\ConnectorSyncRun;
use App\Entity\SourceConnector;
use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use App\JobDiscovery\Domain\Connector\JobSourceConnector;
use PHPUnit\Framework\TestCase;

final class SourceConnectorStateTest extends TestCase
{
    public function testConfiguredConnectorLifecycleIsReported(): void
    {
        $connector = new SourceConnector($this->connector(true));

        $initial = $connector->toArray(900);
        self::assertSame('NEVER_SYNCED', $initial['status']);
        self::assertTrue($initial['enabled']);
        self::assertTrue($initial['configured']);
        self::assertTrue($initial['collectionAllowed']);
        self::assertSame('ALLOWED', $initial['policy']['complianceStatus']);
        self::assertSame(4, $initial['policy']['maxRequestsPerSync']);
        self::assertTrue($initial['due']);

        $connector->markRunning();
        self::assertSame('RUNNING', $connector->toArray(900)['status']);

        $connector->complete(12, 5, 2, 4, 1);
        $completed = $connector->toArray(900);
        self::assertSame('PARTIAL', $completed['status']);
        self::assertSame([
            'received' => 12,
            'imported' => 5,
            'merged' => 2,
            'duplicates' => 4,
            'failed' => 1,
        ], $completed['lastResult']);
        self::assertFalse($completed['due']);
    }

    public function testDisabledAndMisconfiguredStatesAreExplicit(): void
    {
        $connector = new SourceConnector($this->connector(false));
        self::assertSame('MISCONFIGURED', $connector->toArray(900)['status']);
        self::assertFalse($connector->toArray(900)['due']);

        $connector->setEnabled(false);
        self::assertSame('DISABLED', $connector->toArray(900)['status']);

        $connector->setEnabled(true);
        self::assertSame('MISCONFIGURED', $connector->toArray(900)['status']);
    }

    public function testConnectorWithoutExplicitPolicyIsBlockedEvenWhenForced(): void
    {
        $connector = new SourceConnector($this->unreviewedConnector());
        $payload = $connector->toArray(900);

        self::assertSame('COMPLIANCE_BLOCKED', $payload['status']);
        self::assertSame('UNDER_REVIEW', $payload['policy']['complianceStatus']);
        self::assertFalse($payload['collectionAllowed']);
        self::assertFalse($payload['due']);

        $this->expectException(\LogicException::class);
        $connector->markRunning();
    }

    public function testSyncRunCapturesCountersAndDiagnostics(): void
    {
        $connector = new SourceConnector($this->connector(true));
        $run = new ConnectorSyncRun($connector, 'manual');
        $run->complete(4, 2, 1, 0, 1, 'Une offre est invalide.', [
            'errors' => ['Description absente.'],
        ]);

        $payload = $run->toArray();
        self::assertSame('PARTIAL', $payload['status']);
        self::assertSame('manual', $payload['trigger']);
        self::assertSame('test-source', $payload['connector']['code']);
        self::assertSame(4, $payload['received']);
        self::assertSame(2, $payload['imported']);
        self::assertSame(1, $payload['merged']);
        self::assertSame(['errors' => ['Description absente.']], $payload['details']);
        self::assertIsInt($payload['durationMs']);
    }

    private function connector(bool $configured): GovernedJobSourceConnector
    {
        return new class($configured) implements GovernedJobSourceConnector {
            public function __construct(private bool $configured)
            {
            }

            public function code(): string
            {
                return 'test-source';
            }

            public function name(): string
            {
                return 'Test Source';
            }

            public function mode(): ConnectorMode
            {
                return ConnectorMode::SCRAPING_HTTP;
            }

            public function policy(): ConnectorPolicy
            {
                return new ConnectorPolicy(
                    ConnectorComplianceStatus::ALLOWED,
                    new \DateTimeImmutable('2026-08-05'),
                    'Source de test autorisée.',
                    maxRequestsPerSync: 4,
                    dailyQuota: 20,
                    minimumDelayMilliseconds: 500,
                    respectsRobotsTxt: true,
                );
            }

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function configurationMessage(): ?string
            {
                return $this->configured ? null : 'Configuration absente.';
            }

            public function search(array $targetJobs, array $skills): array
            {
                return [];
            }
        };
    }

    private function unreviewedConnector(): JobSourceConnector
    {
        return new class implements JobSourceConnector {
            public function code(): string
            {
                return 'unreviewed-source';
            }

            public function name(): string
            {
                return 'Unreviewed Source';
            }

            public function mode(): ConnectorMode
            {
                return ConnectorMode::SCRAPING_HTTP;
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function configurationMessage(): ?string
            {
                return null;
            }

            public function search(array $targetJobs, array $skills): array
            {
                return [];
            }
        };
    }
}
