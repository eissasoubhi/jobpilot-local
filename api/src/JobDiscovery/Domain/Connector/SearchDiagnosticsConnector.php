<?php

declare(strict_types=1);

namespace App\JobDiscovery\Domain\Connector;

interface SearchDiagnosticsConnector
{
    /** @return array<string, mixed> */
    public function searchDiagnostics(): array;
}
