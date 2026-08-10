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

final class CustomScraperExtractionServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-custom-extraction-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testPreviewsStructuredOffersOverControlledHttp(): void
    {
        $html = <<<'HTML'
<html><body>
<script type="application/ld+json">{"@type":"JobPosting","identifier":{"value":"S-10"},"title":"Senior Symfony Developer","hiringOrganization":{"name":"Example Tech"},"url":"/jobs/symfony"}</script>
<a href="/jobs/symfony">Senior Symfony Developer</a>
<a href="/jobs/react">React Developer</a>
</body></html>
HTML;

        $preview = $this->service(new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($html, ['http_code' => 200]),
        ]))->preview($this->source());

        self::assertSame('HTTP', $preview['recommendedMode']);
        self::assertSame('HTTP', $preview['effectiveMode']);
        self::assertFalse($preview['requiresBrowser']);
        self::assertSame(1, $preview['candidateCount']);
        self::assertSame(0, $preview['reliableCount']);
        self::assertSame(0, $preview['detailEnriched']);
        self::assertFalse($preview['detailPriorityApplied']);
        self::assertNull($preview['pagination']['nextUrl']);
        self::assertSame('S-10', $preview['candidates'][0]['externalId']);
        self::assertSame('Senior Symfony Developer', $preview['candidates'][0]['title']);
        self::assertFalse($preview['candidates'][0]['rawData']['quality']['reliable']);
        self::assertSame(200, $preview['http']['statusCode']);
    }

    public function testReportsSafeNextPageWithoutFetchingIt(): void
    {
        $html = <<<'HTML'
<html><body>
<a href="/jobs/symfony">Senior Symfony Developer</a>
<a href="/jobs/react">React Developer</a>
<a rel="next" href="?page=2">Suivant</a>
</body></html>
HTML;

        $preview = $this->service(new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($html, ['http_code' => 200]),
        ]))->preview($this->source());

        self::assertSame('https://jobs.example.test/jobs?page=2', $preview['pagination']['nextUrl']);
        self::assertSame('REL_NEXT', $preview['pagination']['strategy']);
        self::assertSame('HIGH', $preview['pagination']['confidence']);
        self::assertSame(1, $preview['http']['networkRequests']);
    }

    public function testEnrichesLinkCandidateFromBoundedDetailFetch(): void
    {
        $source = $this->source();
        $source->fill(['maxDetails' => 1]);
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
  "identifier":{"value":"DETAIL-42"},
  "title":"Senior Symfony Developer",
  "hiringOrganization":{"name":"Acme France"},
  "url":"https://jobs.example.test/jobs/symfony",
  "employmentType":"FREELANCE",
  "description":"Mission Symfony 6.4 et API Platform sur une plateforme métier, avec PostgreSQL, tests automatisés et travail en équipe produit.",
  "baseSalary":{"value":{"minValue":450,"maxValue":500,"unitText":"DAY"}}
}</script>
</body></html>
HTML;

        $preview = $this->service(new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($listing, ['http_code' => 200]),
            new MockResponse($detail, ['http_code' => 200]),
        ]))->preview($source);

        self::assertSame(2, $preview['candidateCount']);
        self::assertSame(1, $preview['reliableCount']);
        self::assertSame(1, $preview['detailLimit']);
        self::assertSame(1, $preview['detailEnriched']);
        self::assertFalse($preview['detailPriorityApplied']);
        self::assertNull($preview['detailError']);
        self::assertSame(2, $preview['http']['networkRequests']);
        self::assertSame('Acme France', $preview['candidates'][0]['company']);
        self::assertStringContainsString('plateforme métier', $preview['candidates'][0]['description']);
        self::assertSame(450, $preview['candidates'][0]['tjmMin']);
        self::assertSame(500, $preview['candidates'][0]['tjmMax']);
        self::assertTrue($preview['candidates'][0]['rawData']['detailEnriched']);
        self::assertSame('JSON_LD', $preview['candidates'][0]['rawData']['detailExtractionMethod']);
        self::assertTrue($preview['candidates'][0]['rawData']['quality']['reliable']);
        self::assertGreaterThanOrEqual(70, $preview['candidates'][0]['rawData']['quality']['score']);
        self::assertFalse($preview['candidates'][1]['rawData']['quality']['reliable']);
        self::assertSame('link-', substr((string) $preview['candidates'][0]['externalId'], 0, 5));
    }

    public function testAutoModeDoesNotExtractJavascriptShell(): void
    {
        $html = '<html><head><script id="__NEXT_DATA__">{}</script><script src="/_next/app.js"></script></head><body><div id="__next"></div></body></html>';

        $preview = $this->service(new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($html, ['http_code' => 200]),
        ]))->preview($this->source());

        self::assertSame('BROWSER', $preview['recommendedMode']);
        self::assertSame('BROWSER', $preview['effectiveMode']);
        self::assertTrue($preview['requiresBrowser']);
        self::assertSame(0, $preview['candidateCount']);
        self::assertSame(0, $preview['reliableCount']);
        self::assertNull($preview['pagination']['nextUrl']);
        self::assertSame([], $preview['candidates']);
    }

    public function testForcedBrowserModeBlocksBeforeAnyNetworkCall(): void
    {
        $source = $this->source();
        $source->fill(['mode' => 'BROWSER']);
        $service = $this->service(new MockHttpClient(static function (): never {
            throw new \LogicException('Le réseau ne doit pas être appelé.');
        }));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Playwright');
        $service->preview($source);
    }

    public function testRevokedAuthorizationBlocksBeforeAnyNetworkCall(): void
    {
        $source = $this->source();
        $source->fill(['authorizationConfirmed' => false]);
        $service = $this->service(new MockHttpClient(static function (): never {
            throw new \LogicException('Le réseau ne doit pas être appelé.');
        }));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('autorisation');
        $service->preview($source);
    }

    private function source(): CustomScraperSource
    {
        $source = new CustomScraperSource('Example Jobs', 'https://jobs.example.test/jobs');
        $source->fill([
            'mode' => 'AUTO',
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-10',
            'authorizationReference' => 'Autorisation vérifiée pour ce test local.',
            'maxDetails' => 0,
        ]);

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
