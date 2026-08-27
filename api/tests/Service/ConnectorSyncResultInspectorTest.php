<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\JobDiscovery\Application\ConnectorSyncResultInspector;
use PHPUnit\Framework\TestCase;

final class ConnectorSyncResultInspectorTest extends TestCase
{
    public function testReturnsTheErrorForTheRequestedConnectorOnly(): void
    {
        $result = [
            'connectorResults' => [
                ['code' => 'france-travail', 'error' => 'France Travail indisponible'],
                ['code' => 'gmail', 'error' => 'Jeton Gmail expiré'],
            ],
        ];

        self::assertSame(
            'Jeton Gmail expiré',
            ConnectorSyncResultInspector::connectorError($result, 'GMAIL'),
        );
    }

    public function testDoesNotTreatPayloadFailuresAsAConnectorFailure(): void
    {
        $result = [
            'connectorResults' => [
                [
                    'code' => 'gmail',
                    'failed' => 3,
                    'error' => null,
                ],
            ],
        ];

        self::assertNull(ConnectorSyncResultInspector::connectorError($result, 'gmail'));
    }

    public function testReturnsNullWhenTheTargetConnectorDidNotRun(): void
    {
        self::assertNull(ConnectorSyncResultInspector::connectorError([
            'connectorResults' => [
                ['code' => 'adzuna', 'error' => 'Erreur Adzuna'],
            ],
        ], 'gmail'));
    }

    public function testIgnoresMalformedConnectorResults(): void
    {
        self::assertNull(ConnectorSyncResultInspector::connectorError([
            'connectorResults' => ['invalid', null, ['code' => 'gmail', 'error' => '   ']],
        ], 'gmail'));
    }
}
