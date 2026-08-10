<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Infrastructure\Scraping\Html\CustomScraperAiPageContextBuilder;
use App\Service\Ai\AiMatchingConfigurationStore;
use App\Service\Ai\AiQuotaManager;
use App\Service\Ai\ConfiguredGeminiCustomScraperExtractor;
use App\Service\Ai\CustomScraperAiExtractionCache;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConfiguredGeminiCustomScraperExtractorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-configured-custom-ai-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testSecondIdenticalExtractionUsesCacheAndSkipsGemini(): void
    {
        $payload = [
            'steps' => [[
                'type' => 'model_output',
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'offers' => [[
                            'title' => 'Senior Symfony Developer',
                            'sourceUrl' => 'https://jobs.example.test/jobs/symfony',
                            'company' => 'Acme',
                            'location' => 'Paris',
                            'contractType' => 'Freelance',
                            'workMode' => 'Hybride',
                        ]],
                    ], JSON_THROW_ON_ERROR),
                ]],
            ]],
            'usage' => ['total_input_tokens' => 200],
        ];
        $http = new MockHttpClient([
            new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);
        $extractor = $this->extractor($http, true);
        $html = '<html><body><main><a href="/jobs/symfony">Symfony opportunity</a></main></body></html>';

        $first = $extractor->extract($html, 'https://jobs.example.test/jobs', 'Example Jobs');
        $second = $extractor->extract($html, 'https://jobs.example.test/jobs', 'Example Jobs');

        self::assertCount(1, $first);
        self::assertSame($first, $second);
        self::assertSame(1, $http->getRequestsCount());
    }

    public function testDisabledAiConfigurationSkipsProviderAndQuota(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new \LogicException('Gemini ne doit pas être appelé.');
        });
        $extractor = $this->extractor($http, false);

        self::assertSame([], $extractor->extract(
            '<html><body><a href="/jobs/php">PHP opportunity</a></body></html>',
            'https://jobs.example.test/jobs',
            'Example Jobs',
        ));
        self::assertSame(0, $http->getRequestsCount());
    }

    private function extractor(MockHttpClient $http, bool $enabled): ConfiguredGeminiCustomScraperExtractor
    {
        return new ConfiguredGeminiCustomScraperExtractor(
            new AiMatchingConfigurationStore(
                $this->directory.'/private',
                '',
                $enabled,
                'test-key',
                'gemini-3.5-flash-lite',
                100,
                100_000,
                1_000,
                80,
            ),
            new AiQuotaManager($this->directory.'/private'),
            new CustomScraperAiExtractionCache($this->directory.'/private'),
            $http,
            new NullLogger(),
            new CustomScraperAiPageContextBuilder(),
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
