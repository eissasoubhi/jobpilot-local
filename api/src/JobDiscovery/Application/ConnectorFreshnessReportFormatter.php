<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

final class ConnectorFreshnessReportFormatter
{
    /**
     * @param list<array<string, mixed>> $connectors
     */
    public function toJson(array $connectors, int $intervalSeconds): string
    {
        $alerts = array_values(array_filter(
            $connectors,
            static fn (array $connector): bool => (bool) ($connector['alert'] ?? false),
        ));

        return json_encode([
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'intervalSeconds' => max(900, $intervalSeconds),
            'status' => $alerts === [] ? 'OK' : 'ALERT',
            'alertCount' => count($alerts),
            'connectorCount' => count($connectors),
            'connectors' => $connectors,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
