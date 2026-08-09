<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericDomCompactor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericHtmlModeDetector;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingStateStore;
use App\JobDiscovery\Infrastructure\Scraping\Http\RobotsTxtGuard;
use App\Service\Ai\DomOfferExtractorInterface;
use App\Service\CustomScraperExtractionPreviewService;
use App\Service\CustomScraperHttpPageFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CustomScraperExtractionPreviewServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-custom-preview-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testCallsAiForServerRenderedDomAndReturnsOnlyPreviewData(): void
    {
        $html = <<<'HTML'
<!doctype html><html><body><main>
<h1>Nos offres</h1>
<a href="/jobs/123">Senior Symfony Developer</a>
<script type="application/ld+json">{"@context":"https://schema.org","@type":"JobPosting","title":"Senior Symfony Developer"}</script>
</main></body></html>
HTML;

        $extractor = new class implements DomOfferExtractorInterface {
            public int $calls = 0;

            public function extract(string $sourceName, string $domain, string $pageUrl, string $dom): array
            {
                ++$this->calls;
                TestCase::assertSame('Example Jobs', $sourceName);
                TestCase::assertSame('jobs.example.test', $domain);
                TestCase::assertStringContainsString('JobPosting', $dom);

                return [
                    'offers' => [[
                        'title' => 'Senior Symfony Developer',
                        'company' => 'Acme',
                        'location' => 'Paris',
                        'contractType' => 'Freelance',
                        'workMode' => 'HYBRID',
                        'salaryMin' => null,
                        'salaryMax' => null,
                        'tjmMin' => 500,
                        'tjmMax' => 550,
                        'publishedAt' => '2026-08-09',
                        'description' => 'Symfony API',
                        'sourceUrl' => 'https://jobs.example.test/jobs/123',
                        'technologies' => ['PHP', 'Symfony'],
                    ]],
                    'confidence' => 0.95,
                    'notes' => [],
                    'model' => 'gemini-test',
                    'cacheHit' => false,
                ];
            }
        };

        $preview = $this->service(new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($html, ['http_code' => 200]),
        ]), $extractor)->preview($this->source());

        self::assertSame('HTTP', $preview['recommendedMode']);
        self::assertSame('HTTP', $preview['effectiveMode']);
        self::assertFalse($preview['requiresBrowser']);
        self::assertTrue($preview['aiCalled']);
        self::assertSame(1, $extractor->calls);
        self::assertCount(1, $preview['offers']);
        self::assertSame('gemini-test', $preview['ai']['model']);
        self::assertSame(1, $preview['dom']['structuredDataBlocks']);
        self::assertArrayNotHasKey('content', $preview['dom']);
    }

    public function testSkipsGeminiWhenAutoRequiresBrowser(): void
    {
        $html = <<<'HTML'
<!doctype html><html><head>
<script id="__NEXT_DATA__" type="application/json">{"buildId":"abc"}</script>
<script src="/_next/static/chunks/app.js"></script>
<script src="/_next/static/chunks/jobs.js"></script>
</head><body><div id="__next"></div></body></html>
HTML;

        $extractor = new class implements DomOfferExtractorInterface {
            public int $calls = 0;

            public function extract(string $sourceName, string $domain, string $pageUrl, string $dom): array
            {
                ++$this->calls;
                throw new \LogicException('Gemini ne doit pas être appelé avant le rendu Browser.');
            }
        };

        $preview = $this->service(new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($html, ['http_code' => 200]),
        ]), $extractor)->preview($this->source());

        self::assertSame('BROWSER', $preview['recommendedMode']);
        self::assertSame('BROWSER', $preview['effectiveMode']);
        self::assertTrue($preview['requiresBrowser']);
        self::assertFalse($preview['aiCalled']);
        self::assertSame(0, $extractor->calls);
        self::assertSame([], $preview['offers']);
        self::assertNull($preview['ai']);
        self::assertNull($preview['dom']);
        self::assertStringContainsString('Aucun quota Gemini', $preview['message']);
    }

    private function source(): CustomScraperSource
    {
        $source = new CustomScraperSource('Example Jobs', 'https://jobs.example.test/jobs');
        $source->fill([
            'mode' => 'AUTO',
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-09',
            'authorizationReference' => 'Autorisation vérifiée pour ce test local.',
        ]);

        return $source;
    }

    private function service(MockHttpClient $http, DomOfferExtractorInterface $extractor): CustomScraperExtractionPreviewService
    {
        $controlled = new ControlledHttpScrapingClient(
            $http,
            new HttpScrapingStateStore($this->directory.'/state'),
            new RobotsTxtGuard($http, $this->directory.'/robots'),
        );

        return new CustomScraperExtractionPreviewService(
            new CustomScraperHttpPageFetcher($controlled),
            new GenericHtmlModeDetector(),
            new GenericDomCompactor(),
            $extractor,
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
