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
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingResult;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingStateStore;
use App\JobDiscovery\Infrastructure\Scraping\Http\RobotsTxtGuard;
use App\Service\CustomScraperExtractionService;
use App\Service\CustomScraperListingPageFetcher;
use App\Service\CustomScraperMultiSearchBudgetPlanner;
use App\Service\CustomScraperMultiSearchListingCollector;
use App\Service\CustomScraperSearchPlanner;
use App\Service\CustomScraperSearchResultMerger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CustomScraperMultiSearchCollectionIntegrationTest extends TestCase
{
    public function testNormalCollectionUsesKeywordSearchesThenEnrichesOneDeduplicatedDetail(): void
    {
        $source = $this->source();
        $listingFetcher = $this->createMock(CustomScraperListingPageFetcher::class);
        $listingFetcher->expects(self::exactly(2))
            ->method('fetch')
            ->willReturnCallback(fn (string $connectorCode, string $url): HttpScrapingResult => match ($url) {
                'https://jobs.example.com/search?q=PHP' => $this->listingResponse($url, 'PHP'),
                'https://jobs.example.com/search?q=Symfony' => $this->listingResponse($url, 'Symfony'),
                default => throw new \RuntimeException('URL de recherche inattendue : '.$url),
            });

        $detailRequests = [];
        $http = new MockHttpClient(function (string $method, string $url) use (&$detailRequests): MockResponse {
            if ($url === 'https://jobs.example.com/robots.txt') {
                return new MockResponse("User-agent: *\nAllow: /\n", [
                    'http_code' => 200,
                    'response_headers' => ['content-type: text/plain'],
                ]);
            }

            $detailRequests[] = [$method, $url];
            self::assertSame('GET', $method);
            self::assertSame('https://jobs.example.com/job/job-123', $url);

            return new MockResponse($this->detailHtml(), [
                'http_code' => 200,
                'response_headers' => ['content-type: text/html; charset=UTF-8'],
            ]);
        });

        $stateDirectory = sys_get_temp_dir().'/jobpilot-multisearch-state-'.bin2hex(random_bytes(4));
        $robotsDirectory = sys_get_temp_dir().'/jobpilot-multisearch-robots-'.bin2hex(random_bytes(4));
        $controlled = new ControlledHttpScrapingClient(
            $http,
            new HttpScrapingStateStore($stateDirectory),
            new RobotsTxtGuard($http, $robotsDirectory),
        );

        $listingExtractor = new GenericJobListingExtractor();
        $collector = new CustomScraperMultiSearchListingCollector(
            new CustomScraperMultiSearchBudgetPlanner(new CustomScraperSearchPlanner()),
            new CustomScraperSearchResultMerger(),
            $listingFetcher,
            $listingExtractor,
            new GenericPaginationDetector(),
            new GenericHtmlModeDetector(),
        );
        $service = new CustomScraperExtractionService(
            $controlled,
            new GenericHtmlModeDetector(),
            $listingExtractor,
            new GenericJobDetailExtractor($listingExtractor),
            new CustomScraperOfferQualityEvaluator(),
            new GenericPaginationDetector(),
            new CustomScraperDetailPriority(),
            $collector,
        );

        try {
            $result = $service->collect($source, ['Développeur PHP Symfony'], ['PHP', 'Symfony']);
        } finally {
            $this->removeDirectory($stateDirectory);
            $this->removeDirectory($robotsDirectory);
        }

        self::assertSame('MULTI_KEYWORD_SEARCH', $result['pagination']['strategy']);
        self::assertSame(2, $result['pagination']['pagesFetched']);
        self::assertSame(2, $result['searches']['searchCount']);
        self::assertSame(1, $result['searches']['duplicateCount']);
        self::assertSame(1, $result['candidateCount']);
        self::assertSame(1, $result['detailEnriched']);
        self::assertTrue($result['detailPriorityApplied']);
        self::assertSame(3, $result['http']['networkRequests']);
        self::assertCount(1, $detailRequests, 'Une offre trouvée par deux mots-clés ne doit charger sa fiche détail qu’une fois.');

        $candidate = $result['candidates'][0];
        self::assertSame('job-123', $candidate['externalId']);
        self::assertSame(['PHP', 'Symfony'], $candidate['rawData']['discoveredByKeywords']);
        self::assertTrue($candidate['rawData']['detailEnriched']);
        self::assertTrue($candidate['rawData']['quality']['reliable']);
        self::assertSame(1, $result['reliableCount']);
    }

    private function source(): CustomScraperSource
    {
        $source = (new CustomScraperSource('Example Jobs', 'https://jobs.example.com/jobs'))->fill([
            'searchUrlTemplate' => 'https://jobs.example.com/search?q={keyword}',
            'searchKeywords' => ['PHP', 'Symfony'],
            'maxPages' => 1,
            'maxDetails' => 5,
            'mode' => 'HTTP',
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-12',
            'authorizationReference' => 'Autorisation écrite de test.',
        ]);

        $id = new \ReflectionProperty(CustomScraperSource::class, 'id');
        $id->setValue($source, 42);

        return $source;
    }

    private function listingResponse(string $url, string $keyword): HttpScrapingResult
    {
        $posting = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'identifier' => ['value' => 'job-123'],
            'title' => $keyword === 'Symfony' ? 'Développeur PHP / Symfony' : 'Développeur PHP',
            'description' => '',
            'hiringOrganization' => ['name' => 'Acme'],
            'jobLocation' => ['address' => ['addressLocality' => 'Paris']],
            'employmentType' => 'CONTRACTOR',
            'url' => 'https://jobs.example.com/job/job-123',
        ];

        return new HttpScrapingResult(
            $url,
            200,
            '<script type="application/ld+json">'
                .json_encode($posting, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                .'</script>',
            [],
            1,
            false,
        );
    }

    private function detailHtml(): string
    {
        $posting = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'identifier' => ['value' => 'job-123'],
            'title' => 'Développeur PHP / Symfony',
            'description' => str_repeat('Développement Symfony PHP, API REST, tests automatisés et architecture propre. ', 8),
            'hiringOrganization' => ['name' => 'Acme'],
            'jobLocation' => ['address' => ['addressLocality' => 'Paris']],
            'employmentType' => 'CONTRACTOR',
            'url' => 'https://jobs.example.com/job/job-123',
        ];

        return '<script type="application/ld+json">'
            .json_encode($posting, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            .'</script>';
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (glob($directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($directory);
    }
}
