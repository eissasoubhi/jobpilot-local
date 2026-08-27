<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

final class ConnectorSyncResultInspector
{
    /** @param array<string, mixed> $result */
    public static function connectorError(array $result, string $connectorCode): ?string
    {
        $connectorCode = strtolower(trim($connectorCode));
        if ($connectorCode === '') {
            return null;
        }

        $connectorResults = $result['connectorResults'] ?? null;
        if (!is_array($connectorResults)) {
            return null;
        }

        foreach ($connectorResults as $connectorResult) {
            if (!is_array($connectorResult)) {
                continue;
            }

            $code = strtolower(trim((string) ($connectorResult['code'] ?? '')));
            if ($code !== $connectorCode) {
                continue;
            }

            $error = trim((string) ($connectorResult['error'] ?? ''));

            return $error !== '' ? $error : null;
        }

        return null;
    }
}
