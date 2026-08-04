<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

use App\JobDiscovery\Domain\Connector\JobSourceConnector;

final class ConnectorRegistry
{
    /** @var array<string, JobSourceConnector> */
    private array $connectors = [];

    /** @param iterable<JobSourceConnector> $connectors */
    public function __construct(iterable $connectors)
    {
        foreach ($connectors as $connector) {
            $code = self::normalizeCode($connector->code());
            if ($code === '') {
                throw new \InvalidArgumentException('Un connecteur doit déclarer un code non vide.');
            }
            if (isset($this->connectors[$code])) {
                throw new \LogicException(sprintf('Le code de connecteur "%s" est déclaré plusieurs fois.', $code));
            }

            $this->connectors[$code] = $connector;
        }

        ksort($this->connectors);
    }

    /** @return list<JobSourceConnector> */
    public function all(): array
    {
        return array_values($this->connectors);
    }

    public function get(string $code): JobSourceConnector
    {
        $normalized = self::normalizeCode($code);
        if (!isset($this->connectors[$normalized])) {
            throw new \InvalidArgumentException(sprintf('Connecteur inconnu : %s.', $code));
        }

        return $this->connectors[$normalized];
    }

    public function has(string $code): bool
    {
        return isset($this->connectors[self::normalizeCode($code)]);
    }

    private static function normalizeCode(string $code): string
    {
        return strtolower(trim($code));
    }
}
