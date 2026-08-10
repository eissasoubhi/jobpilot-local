<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

use App\JobDiscovery\Domain\Connector\JobSourceConnector;

final class ConnectorRegistry
{
    /** @var array<string, JobSourceConnector> */
    private array $staticConnectors = [];

    /** @var list<DynamicJobSourceConnectorProvider> */
    private array $dynamicProviders = [];

    /**
     * @param iterable<JobSourceConnector> $connectors
     * @param iterable<DynamicJobSourceConnectorProvider> $dynamicProviders
     */
    public function __construct(iterable $connectors, iterable $dynamicProviders = [])
    {
        foreach ($connectors as $connector) {
            $code = self::normalizeCode($connector->code());
            if ($code === '') {
                throw new \InvalidArgumentException('Un connecteur doit déclarer un code non vide.');
            }
            if (isset($this->staticConnectors[$code])) {
                throw new \LogicException(sprintf('Le code de connecteur "%s" est déclaré plusieurs fois.', $code));
            }

            $this->staticConnectors[$code] = $connector;
        }

        foreach ($dynamicProviders as $provider) {
            $this->dynamicProviders[] = $provider;
        }

        ksort($this->staticConnectors);
    }

    /** @return list<JobSourceConnector> */
    public function all(): array
    {
        return array_values($this->resolvedConnectors());
    }

    public function get(string $code): JobSourceConnector
    {
        $normalized = self::normalizeCode($code);
        $connectors = $this->resolvedConnectors();
        if (!isset($connectors[$normalized])) {
            throw new \InvalidArgumentException(sprintf('Connecteur inconnu : %s.', $code));
        }

        return $connectors[$normalized];
    }

    public function has(string $code): bool
    {
        return isset($this->resolvedConnectors()[self::normalizeCode($code)]);
    }

    /** @return array<string, JobSourceConnector> */
    private function resolvedConnectors(): array
    {
        $connectors = $this->staticConnectors;

        foreach ($this->dynamicProviders as $provider) {
            foreach ($provider->connectors() as $connector) {
                $code = self::normalizeCode($connector->code());
                if ($code === '') {
                    throw new \InvalidArgumentException('Un connecteur dynamique doit déclarer un code non vide.');
                }
                if (isset($connectors[$code])) {
                    throw new \LogicException(sprintf('Le code de connecteur "%s" est déclaré plusieurs fois.', $code));
                }
                $connectors[$code] = $connector;
            }
        }

        ksort($connectors);

        return $connectors;
    }

    private static function normalizeCode(string $code): string
    {
        return strtolower(trim($code));
    }
}
