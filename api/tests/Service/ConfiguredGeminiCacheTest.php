<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\Ai\AiMatchingCache;
use App\Service\Ai\AiMatchingConfigurationStore;
use App\Service\Ai\AiQuotaManager;
use App\Service\Ai\ConfiguredGeminiJobMatchAnalyzer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConfiguredGeminiCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-ai-cache-integration-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testSecondIdenticalAnalysisUsesNoAdditionalRequestOrQuota(): void
    {
        $configuration = new AiMatchingConfigurationStore(
            $this->directory,
            'test-encryption-key',
            true,
            'local-test-credential',
            'test-model',
            15,
            250000,
            500,
            100,
        );
        $quota = new AiQuotaManager($this->directory);
        $client = new MockHttpClient([self::response()]);
        $analyzer = new ConfiguredGeminiJobMatchAnalyzer(
            $client,
            new NullLogger(),
            $configuration,
            $quota,
            new AiMatchingCache($this->directory),
        );

        $first = $analyzer->analyze($this->job(), $this->settings());
        $second = $analyzer->analyze($this->job(), $this->settings());

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame($first->toArray(), $second->toArray());
        self::assertSame(1, $client->getRequestsCount());

        $usage = $quota->status('gemini', 'test-model', $configuration->effective()['quota']);
        self::assertSame(1, $usage['rpmUsed']);
        self::assertSame(120, $usage['tpmUsed']);
        self::assertSame(1, $usage['rpdUsed']);
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
            'mustHaves' => ['PHP', 'Symfony'],
            'niceToHaves' => [],
            'missingMustHaves' => [],
            'conflicts' => [],
            'explanation' => 'Strong primary-stack fit.',
        ];

        return new MockResponse(json_encode([
            'usage' => ['total_input_tokens' => 120],
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
