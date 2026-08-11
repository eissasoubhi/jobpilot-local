<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericHtmlModeDetector;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingStateStore;
use App\JobDiscovery\Infrastructure\Scraping\Http\RobotsTxtGuard;
use App\Service\CustomScraperDiagnosticService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CustomScraperDiagnosticServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-custom-diagnostic-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testDetectsServerRenderedJobListingsAsHttp(): void
    {
        $html = <<<'HTML'
<!doctype html><html><body><main>
<h1>Nos offres</h1>
<a href="/offres/developpeur-php">Développeur PHP CDI</a>
<a href="/offres/lead-symfony">Lead Symfony CDI</a>
<script type="application/ld+json">{"@context":"https://schema.org","@type":"JobPosting","title":"Développeur PHP"}</script>
</main></body></html>
HTML;

        $diagnostic = $this->service(new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($html, ['http_code' => 200, 'response_headers' => ['content-type: text/html; charset=UTF-8']]),
        ]))->diagnose($this->source());

        self::assertSame('HTTP', $diagnostic['recommendedMode']);
        self::assertSame('HTTP', $diagnostic['effectiveMode']);
        self::assertSame('HIGH', $diagnostic['confidence']);
        self::assertSame(1, $diagnostic['signals']['jobStructuredData']);
        self::assertGreaterThanOrEqual(2, $diagnostic['signals']['jobLikeLinks']);
        self::assertSame(200, $diagnostic['http']['statusCode']);
        self::assertSame('https://jobs.example.test/robots.txt', $diagnostic['robots']['requestedUrl']);
        self::assertSame(404, $diagnostic['robots']['statusCode']);
        self::assertSame(0, $diagnostic['robots']['redirects']);
    }

    public function testRobotsCanonicalRedirectIsExposedInDiagnostic(): void
    {
        $html = '<html><body><a href="/offres/php">Développeur PHP Symfony</a></body></html>';

        $diagnostic = $this->service(new MockHttpClient([
            new MockResponse('', [
                'http_code' => 301,
                'response_headers' => ['location: /robots-canonical.txt'],
            ]),
            new MockResponse("User-agent: *\nAllow: /\n", ['http_code' => 200]),
            new MockResponse($html, ['http_code' => 200]),
        ]))->diagnose($this->source());

        self::assertSame('https://jobs.example.test/robots.txt', $diagnostic['robots']['requestedUrl']);
        self::assertSame('https://jobs.example.test/robots-canonical.txt', $diagnostic['robots']['finalUrl']);
        self::assertSame(200, $diagnostic['robots']['statusCode']);
        self::assertSame(1, $diagnostic['robots']['redirects']);
        self::assertFalse($diagnostic['robots']['fromCache']);
        self::assertSame(200, $diagnostic['http']['statusCode']);
    }

    public function testDetectsJavascriptApplicationShellAsBrowserCandidate(): void
    {
        $html = <<<'HTML'
<!doctype html><html><head>
<script id="__NEXT_DATA__" type="application/json">{"buildId":"abc"}</script>
<script src="/_next/static/chunks/app.js"></script>
<script src="/_next/static/chunks/jobs.js"></script>
</head><body><div id="__next"></div></body></html>
HTML;

        $diagnostic = $this->service(new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($html, ['http_code' => 200]),
        ]))->diagnose($this->source());

        self::assertSame('BROWSER', $diagnostic['recommendedMode']);
        self::assertSame('HIGH', $diagnostic['confidence']);
        self::assertTrue($diagnostic['browserVerificationRequired']);
        self::assertGreaterThanOrEqual(2, $diagnostic['signals']['javascriptMarkers']);
    }

    public function testForcedModeWinsOverRecommendation(): void
    {
        $source = $this->source();
        $source->fill(['mode' => 'HTTP']);
        $html = '<html><body><div id="app"></div><script type="module" src="/app.js"></script><script>window.__NUXT__={}</script></body></html>';

        $diagnostic = $this->service(new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($html, ['http_code' => 200]),
        ]))->diagnose($source);

        self::assertSame('BROWSER', $diagnostic['recommendedMode']);
        self::assertSame('HTTP', $diagnostic['configuredMode']);
        self::assertSame('HTTP', $diagnostic['effectiveMode']);
    }

    public function testRevokedAuthorizationBlocksBeforeNetwork(): void
    {
        $source = $this->source();
        $source->fill(['authorizationConfirmed' => false]);
        $service = $this->service(new MockHttpClient(static function (): never {
            throw new \LogicException('Le réseau ne doit pas être appelé.');
        }));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('autorisation');
        $service->diagnose($source);
    }

    private function source(): CustomScraperSource
    {
        $source = new CustomScraperSource('Example Jobs', 'https://jobs.example.test/offres');
        $source->fill([
            'mode' => 'AUTO',
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-09',
            'authorizationReference' => 'Autorisation vérifiée pour ce test local.',
        ]);

        return $source;
    }

    private function service(MockHttpClient $http): CustomScraperDiagnosticService
    {
        $robots = new RobotsTxtGuard($http, $this->directory.'/robots');

        return new CustomScraperDiagnosticService(
            new ControlledHttpScrapingClient(
                $http,
                new HttpScrapingStateStore($this->directory.'/state'),
                $robots,
            ),
            new GenericHtmlModeDetector(),
            $robots,
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
