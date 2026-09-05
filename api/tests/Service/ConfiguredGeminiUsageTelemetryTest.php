<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\Ai\AiMatchingCache;
use App\Service\Ai\AiMatchingConfigurationStore;
use App\Service\Ai\AiQuotaManager;
use App\Service\Ai\AiUsageLedger;
use App\Service\Ai\AiUsagePreferencesStore;
use App\Service\Ai\AiUsagePricing;
use App\Service\Ai\ConfiguredGeminiJobMatchAnalyzer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConfiguredGeminiUsageTelemetryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-ai-usage-integration-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testProviderCallAndSubsequentCacheHitAreBothRecorded(): void
    {
        $configuration = new AiMatchingConfigurationStore(
            $this->directory,
            'test-encryption-key',
            true,
            'local-test-credential',
            'gemini-3.5-flash-lite',
            15,
            250000,
            500,
            100,
        );
        $preferences = new AiUsagePreferencesStore($this->directory);
        $ledger = new AiUsageLedger($this->directory, new AiUsagePricing(), $preferences);
        $client = new MockHttpClient([self::response()]);
        $analyzer = new ConfiguredGeminiJobMatchAnalyzer(
            $client,
            new NullLogger(),
            $configuration,
            new AiQuotaManager($this->directory),
            new AiMatchingCache($this->directory),
            $ledger,
        );

        self::assertNotNull($analyzer->analyze($this->job(), $this->settings()));
        self::assertNotNull($analyzer->analyze($this->job(), $this->settings()));
        self::assertSame(1, $client->getRequestsCount());

        $summary = $ledger->dashboard()['summaries']['today'];
        self::assertSame(2, $summary['operations']);
        self::assertSame(1, $summary['providerCalls']);
        self::assertSame(1, $summary['cacheHits']);
        self::assertSame(120, $summary['inputTokens']);
        self::assertSame(20, $summary['outputTokens']);
        self::assertGreaterThan(0.0, $summary['estimatedCostUsd']);
    }

    private function job(): JobOffer
    {
        return (new JobOffer())->fill([
            'title' => 'Senior PHP Symfony Developer',
            'description' => 'Symfony and PHP are the primary stack.',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybrid',
        ]);
    }

    private function settings(): UserSettings
    {
        return (new UserSettings())->fill([
            'targetJobs' => ['Senior PHP Symfony Developer'],
            'skills' => ['PHP', 'Symfony'],
            'exclusions' => ['Stage'],
        ]);
    }

    private static function response(): MockResponse
    {
        $analysis = [
            'score' => 88,
            'confidence' => 0.94,
            'decision' => 'MATCH',
            'primaryRole' => 'Senior PHP Symfony Developer',
            'primaryStack' => ['PHP', 'Symfony'],
            'secondaryStack' => [],
            'phpRelevance' => 'PRIMARY',
            'mustHaves' => ['PHP', 'Symfony'],
            'niceToHaves' => [],
            'missingMustHaves' => [],
            'conflicts' => [],
            'explanation' => 'Strong primary-stack fit.',
        ];

        return new MockResponse(json_encode([
            'usage' => [
                'total_input_tokens' => 120,
                'total_output_tokens' => 20,
                'total_cached_tokens' => 10,
                'total_tokens' => 140,
            ],
            'steps' => [[
                'type' => 'model_output',
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode($analysis, JSON_THROW_ON_ERROR),
                ]],
            ]],
        ], JSON_THROW_ON_ERROR));
    }
}
