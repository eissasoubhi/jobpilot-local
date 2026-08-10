<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\SourceConnector;
use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use App\JobDiscovery\Domain\Connector\ScheduledJobSourceConnector;
use PHPUnit\Framework\TestCase;

final class SourceConnectorScheduleTest extends TestCase
{
    public function testScheduledConnectorOverridesTheGlobalInterval(): void
    {
        $connector = new class implements GovernedJobSourceConnector, ScheduledJobSourceConnector {
            public function code(): string { return 'scheduled-test'; }
            public function name(): string { return 'Scheduled Test'; }
            public function mode(): ConnectorMode { return ConnectorMode::SCRAPING_HTTP; }
            public function isConfigured(): bool { return true; }
            public function configurationMessage(): ?string { return null; }
            public function search(array $targetJobs, array $skills): array { return []; }
            public function syncIntervalSeconds(): int { return 3_600; }
            public function policy(): ConnectorPolicy
            {
                return new ConnectorPolicy(ConnectorComplianceStatus::ALLOWED, new \DateTimeImmutable('2026-08-10'));
            }
        };
        $state = new SourceConnector($connector);
        $state->complete(0, 0, 0, 0, 0);
        $this->setLastSyncedAt($state, new \DateTimeImmutable('-2 hours'));

        self::assertTrue($state->isDue(21_600));
        self::assertSame(3_600, $state->toArray(21_600)['intervalSeconds']);
    }

    public function testNormalConnectorKeepsTheGlobalInterval(): void
    {
        $connector = new class implements GovernedJobSourceConnector {
            public function code(): string { return 'normal-test'; }
            public function name(): string { return 'Normal Test'; }
            public function mode(): ConnectorMode { return ConnectorMode::API; }
            public function isConfigured(): bool { return true; }
            public function configurationMessage(): ?string { return null; }
            public function search(array $targetJobs, array $skills): array { return []; }
            public function policy(): ConnectorPolicy
            {
                return new ConnectorPolicy(ConnectorComplianceStatus::ALLOWED, new \DateTimeImmutable('2026-08-10'));
            }
        };
        $state = new SourceConnector($connector);
        $state->complete(0, 0, 0, 0, 0);
        $this->setLastSyncedAt($state, new \DateTimeImmutable('-2 hours'));

        self::assertFalse($state->isDue(21_600));
        self::assertSame(21_600, $state->toArray(21_600)['intervalSeconds']);
    }

    private function setLastSyncedAt(SourceConnector $state, \DateTimeImmutable $value): void
    {
        $property = new \ReflectionProperty(SourceConnector::class, 'lastSyncedAt');
        $property->setValue($state, $value);
    }
}
