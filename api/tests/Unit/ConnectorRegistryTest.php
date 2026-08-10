<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Application\ConnectorRegistry;
use App\JobDiscovery\Application\DynamicJobSourceConnectorProvider;
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

    public function testItAddsDynamicConnectorsAndKeepsStableOrdering(): void
    {
        $adzuna = $this->connector('adzuna', 'Adzuna');
        $customTwo = $this->connector('custom-scraper-2', 'Second custom source');
        $customOne = $this->connector('custom-scraper-1', 'First custom source');
        $provider = new class([$customTwo, $customOne]) implements DynamicJobSourceConnectorProvider {
            /** @param list<JobSourceConnector> $items */
            public function __construct(private array $items)
            {
            }

            public function connectors(): iterable
            {
                yield from $this->items;
            }
        };
        $registry = new ConnectorRegistry([$adzuna], [$provider]);

        self::assertTrue($registry->has('custom-scraper-1'));
        self::assertSame($customTwo, $registry->get('CUSTOM-SCRAPER-2'));
        self::assertSame([$adzuna, $customOne, $customTwo], $registry->all());
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

    public function testItRejectsDuplicateCodesAcrossStaticAndDynamicConnectors(): void
    {
        $provider = new class($this->connector('adzuna', 'Dynamic duplicate')) implements DynamicJobSourceConnectorProvider {
            public function __construct(private JobSourceConnector $connector)
            {
            }

            public function connectors(): iterable
            {
                yield $this->connector;
            }
        };
        $registry = new ConnectorRegistry([$this->connector('adzuna', 'Adzuna')], [$provider]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('déclaré plusieurs fois');

        $registry->all();
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
