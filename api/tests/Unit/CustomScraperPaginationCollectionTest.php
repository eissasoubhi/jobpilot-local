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

final class CustomScraperPaginationCollectionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-custom-pagination-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testStopsWhenDetectedPaginationLoopsToAnAlreadyVisitedPage(): void
    {
        $source = $this->source(81, 5);
        $pageOne = '<html><body><a href="/jobs/php">PHP Developer</a><a href="/jobs/symfony">Symfony Developer</a><a rel="next" href="?page=2">Next</a></body></html>';
        $pageTwo = '<html><body><a href="/jobs/react">React Developer</a><a href="/jobs/vue">Vue Developer</a><a rel="next" href="https://jobs.example.test/jobs">Next</a></body></html>';

        $result = $this->service(new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($pageOne, ['http_code' => 200]),
            new MockResponse($pageTwo, ['http_code' => 200]),
        ]))->collect($source);

        self::assertSame(2, $result['pagination']['pagesFetched']);
        self::assertTrue($result['pagination']['loopDetected']);
        self::assertSame('LOOP_DETECTED', $result['pagination']['stopReason']);
        self::assertFalse($result['detailPriorityApplied']);
        self::assertSame(2, $result['http']['networkRequests']);
    }

    public function testStopsAtConfiguredPageLimitWithoutFetchingTheDetectedNextPage(): void
    {
        $source = $this->source(82, 2);
        $pageOne = '<html><body><a href="/jobs/php">PHP Developer</a><a href="/jobs/symfony">Symfony Developer</a><a rel="next" href="?page=2">Next</a></body></html>';
        $pageTwo = '<html><body><a href="/jobs/react">React Developer</a><a href="/jobs/vue">Vue Developer</a><a rel="next" href="?page=3">Next</a></body></html>';

        $result = $this->service(new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($pageOne, ['http_code' => 200]),
            new MockResponse($pageTwo, ['http_code' => 200]),
        ]))->collect($source);

        self::assertSame(2, $result['pagination']['pagesFetched']);
        self::assertSame(2, $result['pagination']['pageLimit']);
        self::assertSame('PAGE_LIMIT_REACHED', $result['pagination']['stopReason']);
        self::assertSame('https://jobs.example.test/jobs?page=3', $result['pagination']['nextUrl']);
        self::assertFalse($result['detailPriorityApplied']);
        self::assertSame(2, $result['http']['networkRequests']);
    }

    private function source(int $id, int $maxPages): CustomScraperSource
    {
        $source = new CustomScraperSource('Example Jobs', 'https://jobs.example.test/jobs');
        $source->fill([
            'mode' => 'AUTO',
            'enabled' => true,
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-10',
            'maxPages' => $maxPages,
            'maxDetails' => 0,
        ]);
        $property = new \ReflectionProperty(CustomScraperSource::class, 'id');
        $property->setValue($source, $id);

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
