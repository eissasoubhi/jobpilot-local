<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Ai\AiUsageLedger;
use App\Service\Ai\AiUsagePreferencesStore;
use App\Service\Ai\AiUsagePricing;
use PHPUnit\Framework\TestCase;

final class AiUsageLedgerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-ai-usage-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testDashboardSeparatesProviderCallsCacheHitsAndQuotaBlocks(): void
    {
        $preferences = new AiUsagePreferencesStore($this->directory);
        $preferences->save([
            'billingTier' => 'paid',
            'usdToEurRate' => 0.85,
            'prepaidCreditUsd' => 10,
        ]);
        $ledger = new AiUsageLedger($this->directory, new AiUsagePricing(), $preferences);

        $ledger->record('gemini', 'gemini-3.5-flash-lite', 'job_match', 'provider_success', [
            'total_input_tokens' => 1000,
            'total_output_tokens' => 100,
            'total_cached_tokens' => 200,
            'total_tokens' => 1100,
        ], 120, 'job_offer', 42, 200);
        $ledger->record('gemini', 'gemini-3.5-flash-lite', 'job_match', 'cache_hit', entityType: 'job_offer', entityId: 42);
        $ledger->record('gemini', 'gemini-3.5-flash-lite', 'application_question', 'quota_blocked', entityType: 'job_offer', entityId: 42);

        $settings = $preferences->get();
        $dashboard = $ledger->dashboard(
            null,
            $settings['usdToEurRate'],
            $settings['prepaidCreditUsd'],
            $settings['prepaidCreditSetAt'],
        );
        $today = $dashboard['summaries']['today'];

        self::assertSame(3, $today['operations']);
        self::assertSame(1, $today['providerCalls']);
        self::assertSame(1, $today['cacheHits']);
        self::assertSame(1, $today['quotaBlocked']);
        self::assertSame(50.0, $today['cacheHitRate']);
        self::assertSame(1000, $today['inputTokens']);
        self::assertSame(200, $today['cachedTokens']);
        self::assertNotNull($today['estimatedCostEur']);
        self::assertCount(3, $dashboard['events']);
        self::assertSame('local_estimate', $dashboard['credit']['label']);
        self::assertLessThan(10.0, $dashboard['credit']['estimatedRemainingUsd']);
    }

    public function testLedgerFileDoesNotPersistPromptOrResponseFields(): void
    {
        $preferences = new AiUsagePreferencesStore($this->directory);
        $ledger = new AiUsageLedger($this->directory, new AiUsagePricing(), $preferences);
        $ledger->record('gemini', 'gemini-3.5-flash-lite', 'custom_scraper_extraction', 'provider_failure', [], 50, 'connector_source', 'Example', 500, 'http_error');

        $raw = (string) file_get_contents($this->directory.'/ai-usage-events.json');

        self::assertStringNotContainsString('prompt', strtolower($raw));
        self::assertStringNotContainsString('response', strtolower($raw));
        self::assertStringContainsString('custom_scraper_extraction', $raw);
    }
}
