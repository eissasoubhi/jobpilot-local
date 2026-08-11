<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Application\CustomScraperAiExtractorInterface;
use App\JobDiscovery\Application\CustomScraperAiFallbackCoordinator;
use App\JobDiscovery\Application\CustomScraperAiFallbackPolicy;
use App\JobDiscovery\Application\CustomScraperDetailPriority;
use App\JobDiscovery\Application\CustomScraperOfferQualityEvaluator;
use App\JobDiscovery\Infrastructure\CustomScraping\CustomScraperAiRecoveryService;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericHtmlModeDetector;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobDetailExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobListingExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericPaginationDetector;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingStateStore;
use App\JobDiscovery\Infrastructure\Scraping\Http\RobotsTxtGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CustomScraperAiRecoveryServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-ai-recovery-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testGroundedAiLinkMustStillBeDetailEnrichedAndReliable(): void
    {
        $extractor = new class implements CustomScraperAiExtractorInterface {
            public int $calls = 0;
            public function extract(string $html, string $pageUrl, string $sourceName): array
            {
                ++$this->calls;
                return [[
                    'source' => $sourceName,
                    'sourceUrl' => 'https://jobs.example.test/opportunities/42',
                    'externalId' => 'ai-link-42',
                    'title' => 'Senior Symfony Developer',
                    'company' => '',
                    'location' => '',
                    'contractType' => '',
                    'workMode' => '',
                    'language' => 'fr',
                    'description' => '',
                    'publishedAt' => null,
                    'salaryMin' => null,
                    'salaryMax' => null,
                    'tjmMin' => null,
                    'tjmMax' => null,
                    'rawData' => [
                        'extractionMethod' => 'AI_GROUNDED_LINK',
                        'needsDetailFetch' => true,
                        'detailEnriched' => false,
                    ],
                ]];
            }
        };
        $listing = '<html><body><main><h1>Nos offres emploi</h1><p>'.str_repeat('Offre emploi mission technique Symfony et PHP. ', 20).'</p><a href="/opportunities/42">Découvrir cette opportunité</a></main></body></html>';
        $detail = <<<'HTML'
<html><body><script type="application/ld+json">{
  "@type":"JobPosting",
  "title":"Senior Symfony Developer",
  "url":"https://jobs.example.test/opportunities/42",
  "hiringOrganization":{"name":"Acme France"},
  "employmentType":"FREELANCE",
  "description":"Mission Symfony 6.4, API Platform et PostgreSQL sur une application métier utilisée quotidiennement, avec tests automatisés et revue de code.",
  "baseSalary":{"value":{"minValue":450,"maxValue":500,"unitText":"DAY"}}
}</script></body></html>
HTML;
        $service = $this->service(new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($listing, ['http_code' => 200]),
            new MockResponse($detail, ['http_code' => 200]),
        ]), $extractor);

        $result = $service->recover($this->source(91), ['Symfony Developer'], ['PHP', 'Symfony']);

        self::assertSame(1, $extractor->calls);
        self::assertCount(1, $result['offers']);
        self::assertSame('Acme France', $result['offers'][0]['company']);
        self::assertTrue($result['offers'][0]['rawData']['quality']['reliable']);
        self::assertSame(1, $result['diagnostics']['fallbackAttempts']);
        self::assertSame(1, $result['diagnostics']['detailEnriched']);
        self::assertSame(1, $result['diagnostics']['reliableCount']);
    }

    public function testDeterministicCandidatesBlockAiRecovery(): void
    {
        $extractor = new class implements CustomScraperAiExtractorInterface {
            public int $calls = 0;
            public function extract(string $html, string $pageUrl, string $sourceName): array
            {
                ++$this->calls;
                return [];
            }
        };
        $listing = '<html><body><a href="/jobs/php">PHP Developer</a><a href="/jobs/symfony">Symfony Developer</a></body></html>';
        $service = $this->service(new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($listing, ['http_code' => 200]),
        ]), $extractor);

        $result = $service->recover($this->source(92, 0), ['Symfony Developer'], ['PHP', 'Symfony']);

        self::assertSame(0, $extractor->calls);
        self::assertSame([], $result['offers']);
        self::assertSame(0, $result['diagnostics']['fallbackAttempts']);
    }

    private function source(int $id, int $maxDetails = 1): CustomScraperSource
    {
        $source = new CustomScraperSource('Example Jobs', 'https://jobs.example.test/careers');
        $source->fill([
            'mode' => 'AUTO',
            'enabled' => true,
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-10',
            'maxPages' => 2,
            'maxDetails' => $maxDetails,
        ]);
        $property = new \ReflectionProperty(CustomScraperSource::class, 'id');
        $property->setValue($source, $id);
        return $source;
    }

    private function service(MockHttpClient $http, CustomScraperAiExtractorInterface $extractor): CustomScraperAiRecoveryService
    {
        $listingExtractor = new GenericJobListingExtractor();
        return new CustomScraperAiRecoveryService(
            new ControlledHttpScrapingClient(
                $http,
                new HttpScrapingStateStore($this->directory.'/state'),
                new RobotsTxtGuard($http, $this->directory.'/robots'),
            ),
            new GenericHtmlModeDetector(),
            $listingExtractor,
            new GenericPaginationDetector(),
            new CustomScraperAiFallbackCoordinator($extractor, new CustomScraperAiFallbackPolicy()),
            new CustomScraperDetailPriority(),
            new GenericJobDetailExtractor($listingExtractor),
            new CustomScraperOfferQualityEvaluator(),
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) return;
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $directory.'/'.$entry;
            if (is_dir($path)) $this->removeDirectory($path); else @unlink($path);
        }
        @rmdir($directory);
    }
}
