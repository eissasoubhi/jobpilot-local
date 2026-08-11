<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Application\BrowserRenderClientInterface;
use App\JobDiscovery\Application\CustomScraperBrowserRenderCoordinator;
use App\JobDiscovery\Application\CustomScraperBrowserRenderPolicy;
use App\JobDiscovery\Application\CustomScraperDetailPriority;
use App\JobDiscovery\Application\CustomScraperOfferQualityEvaluator;
use App\JobDiscovery\Infrastructure\CustomScraping\CustomScraperBrowserRecoveryService;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericHtmlModeDetector;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobDetailExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobListingExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericPaginationDetector;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingStateStore;
use App\JobDiscovery\Infrastructure\Scraping\Http\RobotsTxtGuard;
use App\Service\CustomScraperDiagnosticService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CustomScraperBrowserRecoveryServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-browser-recovery-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testForcedBrowserSourceMustPassHttpRobotsPreflightBeforeRenderedOfferIsReturned(): void
    {
        $shell = '<html><head><script id="__NEXT_DATA__">{}</script><script src="/_next/app.js"></script></head><body><div id="__next"></div></body></html>';
        $rendered = <<<'HTML'
<html><body><script type="application/ld+json">{
  "@type":"JobPosting",
  "identifier":{"value":"BROWSER-42"},
  "title":"Senior Symfony Developer",
  "url":"https://jobs.example.test/jobs/symfony",
  "hiringOrganization":{"name":"Browser Acme"},
  "employmentType":"FREELANCE",
  "description":"Mission Symfony 6.4 et API Platform sur une application métier, avec PostgreSQL, tests automatisés, revue de code et collaboration produit.",
  "baseSalary":{"value":{"minValue":450,"maxValue":500,"unitText":"DAY"}}
}</script></body></html>
HTML;
        $http = new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($shell, ['http_code' => 200]),
        ]);
        $browserClient = new class($rendered) implements BrowserRenderClientInterface {
            public int $calls = 0;
            public function __construct(private string $html) {}
            public function isConfigured(): bool { return true; }
            public function render(string $sourceCode, string $url, string $allowedDomain, bool $authorizationApproved, bool $robotsApproved): array
            {
                ++$this->calls;
                return [
                    'requestedUrl' => $url,
                    'finalUrl' => $url,
                    'statusCode' => 200,
                    'title' => 'Jobs',
                    'html' => $this->html,
                    'htmlBytes' => strlen($this->html),
                    'allowedRequests' => 8,
                    'blockedRequests' => 3,
                ];
            }
        };

        $result = $this->service($http, $browserClient)->recover(
            $this->source(101, 'BROWSER'),
            ['Symfony Developer'],
            ['PHP', 'Symfony'],
        );

        self::assertSame(1, $browserClient->calls);
        self::assertCount(1, $result['offers']);
        self::assertSame('BROWSER-42', $result['offers'][0]['externalId']);
        self::assertSame('Browser Acme', $result['offers'][0]['company']);
        self::assertTrue($result['offers'][0]['rawData']['browserRendered']);
        self::assertTrue($result['offers'][0]['rawData']['quality']['reliable']);
        self::assertSame(1, $result['diagnostics']['pagesRendered']);
        self::assertSame(8, $result['diagnostics']['allowedBrowserRequests']);
        self::assertSame(3, $result['diagnostics']['blockedBrowserRequests']);
    }

    public function testUnconfiguredWorkerBlocksBeforeHttpPreflight(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new \LogicException('Le préflight HTTP ne doit pas être appelé sans worker configuré.');
        });
        $browserClient = new class implements BrowserRenderClientInterface {
            public function isConfigured(): bool { return false; }
            public function render(string $sourceCode, string $url, string $allowedDomain, bool $authorizationApproved, bool $robotsApproved): array
            {
                throw new \LogicException('Le worker ne doit pas être appelé.');
            }
        };

        $result = $this->service($http, $browserClient)->recover(
            $this->source(102, 'BROWSER'),
            [],
            [],
        );

        self::assertSame([], $result['offers']);
        self::assertFalse($result['diagnostics']['attempted']);
        self::assertSame('BROWSER_WORKER_NOT_CONFIGURED', $result['diagnostics']['stopReason']);
        self::assertSame(0, $http->getRequestsCount());
    }

    private function source(int $id, string $mode): CustomScraperSource
    {
        $source = new CustomScraperSource('Example Jobs', 'https://jobs.example.test/jobs');
        $source->fill([
            'mode' => $mode,
            'enabled' => true,
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-10',
            'authorizationReference' => 'Autorisation vérifiée pour ce test local.',
            'maxPages' => 1,
            'maxDetails' => 0,
        ]);
        $property = new \ReflectionProperty(CustomScraperSource::class, 'id');
        $property->setValue($source, $id);
        return $source;
    }

    private function service(MockHttpClient $http, BrowserRenderClientInterface $browserClient): CustomScraperBrowserRecoveryService
    {
        $listingExtractor = new GenericJobListingExtractor();
        $robots = new RobotsTxtGuard($http, $this->directory.'/robots');
        $controlled = new ControlledHttpScrapingClient(
            $http,
            new HttpScrapingStateStore($this->directory.'/state'),
            $robots,
        );
        return new CustomScraperBrowserRecoveryService(
            new CustomScraperDiagnosticService($controlled, new GenericHtmlModeDetector(), $robots),
            new CustomScraperBrowserRenderCoordinator($browserClient, new CustomScraperBrowserRenderPolicy()),
            $browserClient,
            $listingExtractor,
            new GenericPaginationDetector(),
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
