<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Application\CustomScraperDetailPriority;
use App\JobDiscovery\Application\CustomScraperOfferQualityEvaluator;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Infrastructure\CustomScraping\CustomScraperJobConnector;
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

final class CustomScraperJobConnectorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-custom-connector-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testReturnsOnlyReliableCandidatesForCanonicalSync(): void
    {
        $source = $this->source(42);
        $listing = <<<'HTML'
<html><body><main>
<a href="/jobs/symfony">Senior Symfony Developer</a>
<a href="/jobs/react">React Developer</a>
</main></body></html>
HTML;
        $detail = <<<'HTML'
<html><body>
<script type="application/ld+json">{
  "@type":"JobPosting",
  "title":"Senior Symfony Developer",
  "url":"https://jobs.example.test/jobs/symfony",
  "hiringOrganization":{"name":"Acme France"},
  "employmentType":"FREELANCE",
  "description":"Mission Symfony 6.4, API Platform et PostgreSQL sur une plateforme métier utilisée quotidiennement, avec tests et revue de code.",
  "baseSalary":{"value":{"minValue":450,"maxValue":500,"unitText":"DAY"}}
}</script>
</body></html>
HTML;
        $connector = new CustomScraperJobConnector(
            $source,
            $this->extraction(new MockHttpClient([
                new MockResponse('', ['http_code' => 404]),
                new MockResponse($listing, ['http_code' => 200]),
                new MockResponse($detail, ['http_code' => 200]),
            ])),
        );

        $offers = $connector->search(['Symfony Developer'], ['PHP', 'Symfony']);

        self::assertSame('custom-scraper-42', $connector->code());
        self::assertSame('Example Jobs', $connector->name());
        self::assertSame(ConnectorMode::SCRAPING_HTTP, $connector->mode());
        self::assertSame('custom-generic-html-v2', $connector->parserVersion());
        self::assertSame(21_600, $connector->syncIntervalSeconds());
        self::assertSame(6, $connector->policy()->maxRequestsPerSync);
        self::assertTrue($connector->isConfigured());
        self::assertCount(1, $offers);
        self::assertSame('Senior Symfony Developer', $offers[0]['title']);
        self::assertSame('Acme France', $offers[0]['company']);
        self::assertTrue($offers[0]['rawData']['quality']['reliable']);

        $diagnostics = $connector->searchDiagnostics();
        self::assertSame(2, $diagnostics['candidateCount']);
        self::assertSame(1, $diagnostics['reliableCount']);
        self::assertSame(1, $diagnostics['filteredByExtractionQuality']);
        self::assertSame(1, $diagnostics['pagesFetched']);
        self::assertSame('NO_NEXT_PAGE', $diagnostics['paginationStopReason']);
        self::assertTrue($diagnostics['detailPriorityApplied']);
        self::assertSame(2, $diagnostics['networkRequests']);
    }

    public function testFollowsDetectedPaginationUpToTheAvailableNextPage(): void
    {
        $source = $this->source(51);
        $source->fill(['maxPages' => 3, 'maxDetails' => 0]);
        $pageOne = <<<'HTML'
<html><body>
<script type="application/ld+json">{
  "@type":"JobPosting",
  "identifier":{"value":"P1"},
  "title":"Symfony Backend Developer",
  "url":"https://jobs.example.test/jobs/symfony-backend",
  "hiringOrganization":{"name":"Acme One"},
  "description":"Mission backend Symfony sur une plateforme métier avec API Platform, PostgreSQL, tests automatisés et revue de code en équipe produit."
}</script>
<a rel="next" href="?page=2">Suivant</a>
</body></html>
HTML;
        $pageTwo = <<<'HTML'
<html><body>
<script type="application/ld+json">{
  "@type":"JobPosting",
  "identifier":{"value":"P2"},
  "title":"PHP API Developer",
  "url":"https://jobs.example.test/jobs/php-api",
  "hiringOrganization":{"name":"Acme Two"},
  "description":"Mission PHP API sur une application métier avec architecture hexagonale, tests automatisés, PostgreSQL et collaboration avec une équipe produit."
}</script>
</body></html>
HTML;
        $connector = new CustomScraperJobConnector(
            $source,
            $this->extraction(new MockHttpClient([
                new MockResponse('', ['http_code' => 404]),
                new MockResponse($pageOne, ['http_code' => 200]),
                new MockResponse($pageTwo, ['http_code' => 200]),
            ])),
        );

        $offers = $connector->search(['Symfony Developer'], ['PHP', 'Symfony']);

        self::assertCount(2, $offers);
        self::assertSame(['P1', 'P2'], array_column($offers, 'externalId'));
        $diagnostics = $connector->searchDiagnostics();
        self::assertSame(2, $diagnostics['pagesFetched']);
        self::assertSame(3, $diagnostics['effectivePageLimit']);
        self::assertSame('NO_NEXT_PAGE', $diagnostics['paginationStopReason']);
        self::assertFalse($diagnostics['paginationLoopDetected']);
        self::assertTrue($diagnostics['detailPriorityApplied']);
        self::assertSame(2, $diagnostics['networkRequests']);
    }

    public function testForcedBrowserSourceIsNotSynchronizableAndDoesNotCallNetwork(): void
    {
        $source = $this->source(7);
        $source->fill(['mode' => 'BROWSER']);
        $connector = new CustomScraperJobConnector(
            $source,
            $this->extraction(new MockHttpClient(static function (): never {
                throw new \LogicException('Le réseau ne doit pas être appelé.');
            })),
        );

        self::assertSame(ConnectorMode::SCRAPING_BROWSER, $connector->mode());
        self::assertFalse($connector->isConfigured());
        self::assertStringContainsString('Playwright', (string) $connector->configurationMessage());
        self::assertSame([], $connector->search([], []));
        self::assertTrue($connector->searchDiagnostics()['requiresBrowser']);
    }

    private function source(int $id): CustomScraperSource
    {
        $source = new CustomScraperSource('Example Jobs', 'https://jobs.example.test/jobs');
        $source->fill([
            'mode' => 'AUTO',
            'enabled' => true,
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-10',
            'authorizationReference' => 'Autorisation vérifiée pour ce test local.',
            'maxPages' => 5,
            'maxDetails' => 1,
        ]);

        $property = new \ReflectionProperty(CustomScraperSource::class, 'id');
        $property->setValue($source, $id);

        return $source;
    }

    private function extraction(MockHttpClient $http): CustomScraperExtractionService
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
