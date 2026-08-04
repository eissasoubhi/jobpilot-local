<?php

declare(strict_types=1);

namespace App\JobDiscovery\Domain\Connector;

interface JobSourceConnector
{
    public function code(): string;

    public function name(): string;

    public function mode(): ConnectorMode;

    public function isConfigured(): bool;

    public function configurationMessage(): ?string;

    /**
     * @param list<string> $targetJobs
     * @param list<string> $skills
     * @return list<array<string, mixed>>
     */
    public function search(array $targetJobs, array $skills): array;
}
