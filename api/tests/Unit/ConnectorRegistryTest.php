<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Application\ConnectorRegistry;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\JobSourceConnector;
use PHPUnit\Framework\TestCase;

final class ConnectorRegistryTest extends TestCase
{
    public function testItIndexesConnectorsByStableCode(): void
    {
        $arbeitnow = $this->connector('arbeitnow', 'Arbeitnow');
        $adzuna = $this->connector('adzuna', 'Adzuna');
        $registry = new ConnectorRegistry([$arbeitnow, $adzuna]);

        self::assertTrue($registry->has('ARBEITNOW'));
        self::assertSame($arbeitnow, $registry->get(' arbeitnow '));
        self::assertSame([$adzuna, $arbeitnow], $registry->all());
    }

    public function testItRejectsDuplicateConnectorCodes(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('déclaré plusieurs fois');

        new ConnectorRegistry([
            $this->connector('adzuna', 'Adzuna France'),
            $this->connector('ADZUNA', 'Adzuna duplicate'),
        ]);
    }

    private function connector(string $code, string $name): JobSourceConnector
    {
        return new class($code, $name) implements JobSourceConnector {
            public function __construct(private string $codeValue, private string $nameValue)
            {
            }

            public function code(): string
            {
                return $this->codeValue;
            }

            public function name(): string
            {
                return $this->nameValue;
            }

            public function mode(): ConnectorMode
            {
                return ConnectorMode::API;
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
