<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GmailDefaultSearchQueryContractTest extends TestCase
{
    public function testRuntimeFallbackMatchesDocumentedFrenchJobAlertQuery(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $service = file_get_contents($projectRoot.'/api/src/Service/GmailService.php');
        $environment = file_get_contents($projectRoot.'/.env.example');

        self::assertIsString($service);
        self::assertIsString($environment);
        self::assertMatchesRegularExpression(
            "/GMAIL_SEARCH_QUERY=['\"]?([^\r\n'\"]+)/",
            $environment,
        );
        preg_match("/GMAIL_SEARCH_QUERY=['\"]?([^\r\n'\"]+)/", $environment, $documentedMatch);
        self::assertArrayHasKey(1, $documentedMatch);

        $documentedQuery = trim($documentedMatch[1]);
        self::assertStringContainsString('emploi', $documentedQuery);
        self::assertStringContainsString('offre', $documentedQuery);
        self::assertStringContainsString($documentedQuery, $service);
    }
}
