<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Application\CustomScraperDetailPriority;
use App\JobDiscovery\Application\CustomScraperOfferQualityEvaluator;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericHtmlModeDetector;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobDetailExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobListingExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericPaginationDetector;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingStateStore;
use App\JobDiscovery\Infrastructure\Scraping\Http\RobotsTxtGuard;
use App\Service\CustomScraperExtractionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CustomScraperDetailPriorityIntegrationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-custom-detail-priority-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testSingleDetailBudgetIsSpentOnProfileRelevantCandidate(): void
    {
        $listing = <<<'HTML'
<html><body><main>
<a href="/jobs/react">React Developer</a>
<a href="/jobs/java">Java Backend Developer</a>
<a href="/jobs/symfony">Senior Symfony Developer</a>
</main></body></html>
HTML;
        $symfonyDetail = <<<'HTML'
<html><body>
<script type="application/ld+json">{
  "@type":"JobPosting",
  "title":"Senior Symfony Developer",
  "url":"https://jobs.example.test/jobs/symfony",
  "hiringOrganization":{"name":"Symfony Company"},
  "employmentType":"FREELANCE",
  "description":"Mission Symfony 6.4 et API Platform sur une application métier, avec PostgreSQL, tests automatisés, revue de code et collaboration produit.",
  "baseSalary":{"value":{"minValue":450,"maxValue":500,"unitText":"DAY"}}
}</script>
</body></html>
HTML;

        $result = $this->service(new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($listing, ['http_code' => 200]),
            new MockResponse($symfonyDetail, ['http_code' => 200]),
        ]))->collect(
            $this->source(),
            ['Symfony Developer', 'Backend PHP'],
            ['PHP', 'Symfony', 'API Platform'],
        );

        self::assertTrue($result['detailPriorityApplied']);
        self::assertSame(1, $result['detailEnriched']);
        self::assertSame(3, $result['candidateCount']);
        self::assertSame(1, $result['reliableCount']);
        self::assertSame('React Developer', $result['candidates'][0]['title']);
        self::assertSame('Java Backend Developer', $result['candidates'][1]['title']);
        self::assertSame('Senior Symfony Developer', $result['candidates'][2]['title']);
        self::assertSame('Symfony Company', $result['candidates'][2]['company']);
        self::assertTrue($result['candidates'][2]['rawData']['quality']['reliable']);
        self::assertFalse($result['candidates'][0]['rawData']['quality']['reliable']);
        self::assertFalse($result['candidates'][1]['rawData']['quality']['reliable']);
        self::assertSame(2, $result['http']['networkRequests']);
    }

    private function source(): CustomScraperSource
    {
        $source = new CustomScraperSource('Example Jobs', 'https://jobs.example.test/jobs');
        $source->fill([
            'mode' => 'AUTO',
            'enabled' => true,
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-10',
            'maxPages' => 1,
            'maxDetails' => 1,
        ]);
        $property = new \ReflectionProperty(CustomScraperSource::class, 'id');
        $property->setValue($source, 91);

        return $source;
    }

    private function service(MockHttpClient $http): CustomScraperExtractionService
    {
        $listingExtractor = new GenericJobListingExtractor();

        return new CustomScraperExtractionService(
            new ControlledHttpScrapingClient(
                $http,
                new HttpScrapingStateStore($this->directory.'/state'),
                new RobotsTxtGuard($http, $this->directory.'/robots'),
            ),
            new GenericHtmlModeDetector(),
            $listingExtractor,
            new GenericJobDetailExtractor($listingExtractor),
            new CustomScraperOfferQualityEvaluator(),
            new GenericPaginationDetector(),
            new CustomScraperDetailPriority(),
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
