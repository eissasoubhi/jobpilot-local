<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Ai\AiMatchingConfigurationStore;
use App\Service\Ai\AiQuotaManager;
use App\Service\Ai\ConfiguredGeminiDomOfferExtractor;
use App\Service\Ai\CustomScraperAiExtractionCache;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConfiguredGeminiDomOfferExtractorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-gemini-dom-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testExtractsNormalizesAndCachesOffersWithoutSecondProviderCall(): void
    {
        $modelOutput = [
            'confidence' => 0.92,
            'notes' => ['Deux cartes emploi détectées.'],
            'offers' => [
                [
                    'title' => 'Senior Symfony Developer',
                    'company' => 'Acme',
                    'location' => 'Paris',
                    'contractType' => 'Freelance',
                    'workMode' => 'HYBRID',
                    'salaryMin' => 0,
                    'salaryMax' => 0,
                    'tjmMin' => 500,
                    'tjmMax' => 550,
                    'publishedAt' => '2026-08-09',
                    'description' => 'Mission Symfony et API.',
                    'sourceUrl' => '/jobs/123?ref=list',
                    'technologies' => ['PHP', 'Symfony', 'PHP'],
                ],
                [
                    'title' => 'Frontend Developer',
                    'company' => 'Acme',
                    'location' => 'Remote',
                    'contractType' => 'CDI',
                    'workMode' => 'REMOTE',
                    'salaryMin' => 50_000,
                    'salaryMax' => 60_000,
                    'tjmMin' => 0,
                    'tjmMax' => 0,
                    'publishedAt' => '',
                    'description' => 'Poste frontend.',
                    'sourceUrl' => 'https://outside.example/jobs/999',
                    'technologies' => ['React'],
                ],
            ],
        ];

        $providerPayload = [
            'steps' => [[
                'type' => 'model_output',
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode($modelOutput, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ]],
            ]],
            'usage' => ['total_input_tokens' => 321],
        ];

        $http = new MockHttpClient([
            new MockResponse(
                json_encode($providerPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ['http_code' => 200, 'response_headers' => ['content-type: application/json']],
            ),
        ]);
        $extractor = $this->extractor($http, true, 'test-key');
        $dom = '<body><a href="/jobs/123?ref=list">Senior Symfony Developer</a></body>';

        $first = $extractor->extract('Example Jobs', 'jobs.example.test', 'https://jobs.example.test/jobs', $dom);
        self::assertFalse($first['cacheHit']);
        self::assertSame('gemini-test', $first['model']);
        self::assertSame(0.92, $first['confidence']);
        self::assertCount(2, $first['offers']);
        self::assertSame('https://jobs.example.test/jobs/123?ref=list', $first['offers'][0]['sourceUrl']);
        self::assertSame(['PHP', 'Symfony'], $first['offers'][0]['technologies']);
        self::assertSame(500, $first['offers'][0]['tjmMin']);
        self::assertNull($first['offers'][0]['salaryMin']);
        self::assertNull($first['offers'][1]['sourceUrl']);
        self::assertNull($first['offers'][1]['publishedAt']);

        $second = $extractor->extract('Example Jobs', 'jobs.example.test', 'https://jobs.example.test/jobs', $dom);
        self::assertTrue($second['cacheHit']);
        self::assertSame($first['offers'], $second['offers']);
        self::assertSame(1, $http->getRequestsCount());
    }

    public function testRequiresConfiguredGeminiBeforeAnyProviderCall(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new \LogicException('Gemini ne doit pas être appelé sans configuration active.');
        });
        $extractor = $this->extractor($http, false, '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Gemini doit être activé');
        $extractor->extract('Example Jobs', 'jobs.example.test', 'https://jobs.example.test/jobs', '<body>Jobs</body>');
    }

    private function extractor(MockHttpClient $http, bool $enabled, string $apiKey): ConfiguredGeminiDomOfferExtractor
    {
        return new ConfiguredGeminiDomOfferExtractor(
            $http,
            new NullLogger(),
            new AiMatchingConfigurationStore(
                $this->directory.'/config',
                'test-encryption-key',
                $enabled,
                $apiKey,
                'gemini-test',
                100,
                1_000_000,
                1_000,
                100,
            ),
            new AiQuotaManager($this->directory.'/quota'),
            new CustomScraperAiExtractionCache($this->directory.'/cache'),
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory.'/'.$entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }
}
