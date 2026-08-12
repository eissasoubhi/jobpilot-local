<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericHtmlModeDetector;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobListingExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericPaginationDetector;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingResult;
use App\Service\CustomScraperListingPageFetcher;
use App\Service\CustomScraperMultiSearchBudgetPlanner;
use App\Service\CustomScraperMultiSearchListingCollector;
use App\Service\CustomScraperSearchPlanner;
use App\Service\CustomScraperSearchResultMerger;
use PHPUnit\Framework\TestCase;

final class CustomScraperMultiSearchListingCollectorTest extends TestCase
{
    public function testKeywordSearchesShareOneBudgetAndDeduplicateCandidates(): void
    {
        $source = $this->source(['PHP', 'Symfony'], 2);
        $calls = [];
        $fetcher = $this->createMock(CustomScraperListingPageFetcher::class);
        $fetcher->method('fetch')->willReturnCallback(
            function (string $connectorCode, string $url, ConnectorPolicy $policy) use (&$calls): HttpScrapingResult {
                $calls[] = [$connectorCode, $url, $policy->maxRequestsPerSync];

                return match ($url) {
                    'https://jobs.example.com/search?q=PHP' => $this->response(
                        $url,
                        $this->jobHtml('job-123', 'Développeur PHP', 'Description PHP courte')
                            .'<a rel="next" href="https://jobs.example.com/search?q=PHP&amp;page=2">Next</a>',
                    ),
                    'https://jobs.example.com/search?q=PHP&page=2' => $this->response(
                        $url,
                        $this->jobHtml('job-456', 'Développeur Vue.js', 'Mission Vue.js'),
                    ),
                    'https://jobs.example.com/search?q=Symfony' => $this->response(
                        $url,
                        $this->jobHtml(
                            'job-123',
                            'Développeur PHP / Symfony',
                            str_repeat('Description Symfony détaillée. ', 12),
                            'Paris',
                            'FULL_TIME',
                        ),
                    ),
                    default => throw new \RuntimeException('URL inattendue dans le test : '.$url),
                };
            },
        );

        $result = $this->collector($fetcher)->collect($source);

        self::assertSame(2, $result['searchCount']);
        self::assertSame(2, $result['executedSearchCount']);
        self::assertSame(4, $result['globalPageBudget']);
        self::assertFalse($result['budgetLimited']);
        self::assertSame(3, $result['networkRequests']);
        self::assertGreaterThanOrEqual(0, $result['durationMs']);
        self::assertSame(3, $result['rawCandidateCount']);
        self::assertSame(1, $result['duplicateCount']);
        self::assertSame(2, $result['candidateCount']);
        self::assertFalse($result['stoppedEarly']);
        self::assertFalse($result['requiresBrowser']);
        self::assertNull($result['globalError']);

        self::assertSame(
            ['custom-scraper-42', 'custom-scraper-42', 'custom-scraper-42'],
            array_column($calls, 0),
        );
        self::assertSame([4, 4, 4], array_column($calls, 2));
        self::assertSame(2, $result['diagnostics'][0]['pagesFetched']);
        self::assertSame([200, 200], $result['diagnostics'][0]['statusCodes']);
        self::assertSame(200, $result['diagnostics'][0]['lastStatusCode']);
        self::assertGreaterThanOrEqual(0, $result['diagnostics'][0]['durationMs']);
        self::assertSame(1, $result['diagnostics'][1]['pagesFetched']);
        self::assertSame([200], $result['diagnostics'][1]['statusCodes']);

        $duplicate = null;
        foreach ($result['candidates'] as $candidate) {
            if (($candidate['externalId'] ?? null) === 'job-123') {
                $duplicate = $candidate;
                break;
            }
        }
        self::assertIsArray($duplicate);
        self::assertSame('Développeur PHP / Symfony', $duplicate['title']);
        self::assertSame(
            ['PHP', 'Symfony'],
            $duplicate['rawData']['discoveredByKeywords'] ?? null,
        );
    }

    public function testTransportFailureStopsBeforeProbingAnotherKeyword(): void
    {
        $source = $this->source(['PHP', 'Symfony'], 2);
        $fetcher = $this->createMock(CustomScraperListingPageFetcher::class);
        $fetcher->expects(self::once())
            ->method('fetch')
            ->willThrowException(new \RuntimeException('HTTP 429 - limitation détectée.'));

        $result = $this->collector($fetcher)->collect($source);

        self::assertTrue($result['stoppedEarly']);
        self::assertFalse($result['requiresBrowser']);
        self::assertSame(1, $result['executedSearchCount']);
        self::assertSame('HTTP 429 - limitation détectée.', $result['globalError']);
        self::assertSame('PAGE_FETCH_ERROR', $result['diagnostics'][0]['stopReason']);
        self::assertSame([], $result['diagnostics'][0]['statusCodes']);
        self::assertNull($result['diagnostics'][0]['lastStatusCode']);
        self::assertSame(0, $result['candidateCount']);
    }

    public function testJavascriptShellStopsBeforeTryingAnotherKeyword(): void
    {
        $source = $this->source(['PHP', 'Symfony'], 2);
        $fetcher = $this->createMock(CustomScraperListingPageFetcher::class);
        $fetcher->expects(self::once())
            ->method('fetch')
            ->willReturn($this->response(
                'https://jobs.example.com/search?q=PHP',
                $this->javascriptShell(),
            ));

        $result = $this->collector($fetcher)->collect($source);

        self::assertTrue($result['requiresBrowser']);
        self::assertTrue($result['stoppedEarly']);
        self::assertNull($result['globalError']);
        self::assertSame(1, $result['executedSearchCount']);
        self::assertSame('BROWSER_REQUIRED', $result['diagnostics'][0]['stopReason']);
        self::assertSame([200], $result['diagnostics'][0]['statusCodes']);
        self::assertSame(0, $result['candidateCount']);
    }

    public function testJavascriptRequirementOnLaterPageAlsoStopsTheWholeSearchPlan(): void
    {
        $source = $this->source(['PHP', 'Symfony'], 2);
        $fetcher = $this->createMock(CustomScraperListingPageFetcher::class);
        $fetcher->expects(self::exactly(2))
            ->method('fetch')
            ->willReturnCallback(fn (string $connectorCode, string $url): HttpScrapingResult => match ($url) {
                'https://jobs.example.com/search?q=PHP' => $this->response(
                    $url,
                    $this->jobHtml('job-123', 'Développeur PHP', 'Mission PHP')
                        .'<a rel="next" href="https://jobs.example.com/search?q=PHP&amp;page=2">Next</a>',
                ),
                'https://jobs.example.com/search?q=PHP&page=2' => $this->response($url, $this->javascriptShell()),
                default => throw new \RuntimeException('Le mot-clé suivant ne doit pas être sondé.'),
            });

        $result = $this->collector($fetcher)->collect($source);

        self::assertTrue($result['requiresBrowser']);
        self::assertTrue($result['stoppedEarly']);
        self::assertSame(1, $result['executedSearchCount']);
        self::assertSame(2, $result['diagnostics'][0]['pagesFetched']);
        self::assertSame('BROWSER', $result['diagnostics'][0]['recommendedMode']);
        self::assertSame('BROWSER_REQUIRED', $result['diagnostics'][0]['stopReason']);
        self::assertSame([200, 200], $result['diagnostics'][0]['statusCodes']);
        self::assertSame(1, $result['candidateCount']);
    }

    private function collector(CustomScraperListingPageFetcher $fetcher): CustomScraperMultiSearchListingCollector
    {
        return new CustomScraperMultiSearchListingCollector(
            new CustomScraperMultiSearchBudgetPlanner(new CustomScraperSearchPlanner()),
            new CustomScraperSearchResultMerger(),
            $fetcher,
            new GenericJobListingExtractor(),
            new GenericPaginationDetector(),
            new GenericHtmlModeDetector(),
        );
    }

    /** @param list<string> $keywords */
    private function source(array $keywords, int $maxPages): CustomScraperSource
    {
        $source = (new CustomScraperSource('Example Jobs', 'https://jobs.example.com/jobs'))->fill([
            'searchUrlTemplate' => 'https://jobs.example.com/search?q={keyword}',
            'searchKeywords' => $keywords,
            'maxPages' => $maxPages,
            'mode' => 'HTTP',
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-12',
            'authorizationReference' => 'Autorisation écrite de test.',
        ]);

        $id = new \ReflectionProperty(CustomScraperSource::class, 'id');
        $id->setValue($source, 42);

        return $source;
    }

    private function response(string $url, string $html): HttpScrapingResult
    {
        return new HttpScrapingResult($url, 200, $html, [], 1, false);
    }

    private function javascriptShell(): string
    {
        return '<html><body><div id="app"></div>'
            .'<script src="/app.js"></script><script src="/chunk.js"></script>'
            .'<script>window.__NEXT_DATA__={};window.__INITIAL_STATE__={};</script>'
            .'</body></html>';
    }

    private function jobHtml(
        string $id,
        string $title,
        string $description,
        string $city = 'Lyon',
        string $employmentType = 'CONTRACTOR',
    ): string {
        $posting = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'identifier' => ['value' => $id],
            'title' => $title,
            'description' => $description,
            'hiringOrganization' => ['name' => 'Acme'],
            'jobLocation' => ['address' => ['addressLocality' => $city]],
            'employmentType' => $employmentType,
            'url' => 'https://jobs.example.com/job/'.rawurlencode($id),
        ];

        return '<script type="application/ld+json">'
            .json_encode($posting, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            .'</script>';
    }
}
