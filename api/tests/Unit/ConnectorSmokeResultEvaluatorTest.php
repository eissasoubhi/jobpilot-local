<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Application\ConnectorSmokeResultEvaluator;
use PHPUnit\Framework\TestCase;

final class ConnectorSmokeResultEvaluatorTest extends TestCase
{
    private ConnectorSmokeResultEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new ConnectorSmokeResultEvaluator();
    }

    public function testSuccessfulCanonicalOutcomePassesSmokeTest(): void
    {
        $assessment = $this->evaluator->evaluate([
            'busy' => false,
            'skipped' => false,
            'connectorResults' => [[
                'code' => 'le-studio-tech',
                'received' => 3,
                'imported' => 1,
                'merged' => 0,
                'duplicates' => 1,
                'profileFiltered' => 1,
                'failed' => 0,
                'error' => null,
            ]],
        ], 'le-studio-tech');

        self::assertTrue($assessment['success']);
        self::assertSame(3, $assessment['metrics']['received']);
        self::assertStringContainsString('Smoke test réussi', $assessment['message']);
    }

    public function testZeroResultsFailUnlessExplicitlyAllowed(): void
    {
        $result = [
            'busy' => false,
            'skipped' => false,
            'connectorResults' => [[
                'code' => 'custom-scraper-42',
                'received' => 0,
                'imported' => 0,
                'merged' => 0,
                'duplicates' => 0,
                'profileFiltered' => 0,
                'failed' => 0,
            ]],
        ];

        $strict = $this->evaluator->evaluate($result, 'custom-scraper-42');
        self::assertFalse($strict['success']);
        self::assertStringContainsString('aucune offre', mb_strtolower($strict['message']));

        $allowed = $this->evaluator->evaluate($result, 'custom-scraper-42', true);
        self::assertTrue($allowed['success']);
        self::assertStringContainsString('zéro résultat explicitement autorisé', $allowed['message']);
    }

    public function testAnyConnectorFailureFailsSmokeTest(): void
    {
        $assessment = $this->evaluator->evaluate([
            'connectorResults' => [[
                'code' => 'le-studio-tech',
                'received' => 2,
                'imported' => 1,
                'merged' => 0,
                'duplicates' => 0,
                'profileFiltered' => 0,
                'failed' => 1,
                'error' => 'Parser cassé.',
            ]],
        ], 'le-studio-tech');

        self::assertFalse($assessment['success']);
        self::assertStringContainsString('1 erreur(s)', $assessment['message']);
        self::assertStringContainsString('Parser cassé', $assessment['message']);
    }

    public function testSkippedBusyAndMissingConnectorResultFailClearly(): void
    {
        $busy = $this->evaluator->evaluate(['busy' => true], 'source');
        self::assertFalse($busy['success']);
        self::assertStringContainsString('déjà en cours', $busy['message']);

        $skipped = $this->evaluator->evaluate([
            'skipped' => true,
            'message' => 'Ce connecteur est désactivé.',
        ], 'source');
        self::assertFalse($skipped['success']);
        self::assertStringContainsString('désactivé', $skipped['message']);

        $missing = $this->evaluator->evaluate([
            'connectorResults' => [['code' => 'another-source']],
        ], 'source');
        self::assertFalse($missing['success']);
        self::assertStringContainsString('absent', $missing['message']);
    }
}
