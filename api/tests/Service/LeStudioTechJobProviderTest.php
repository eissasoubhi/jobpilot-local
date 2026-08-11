<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Infrastructure\Scraping\Html\LeStudioTechMissionParser;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingStateStore;
use App\JobDiscovery\Infrastructure\Scraping\Http\RobotsTxtGuard;
use App\Service\LeStudioTechJobProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LeStudioTechJobProviderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-le-studio-tech-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testUsesControlledPublicHttpScrapingAndEnrichesOnlyRelevantDetail(): void
    {
        $http = new MockHttpClient([
            new MockResponse("User-agent: *\nAllow: /freelances/missions\n", ['http_code' => 200]),
            new MockResponse($this->fixture('le-studio-tech-missions.html'), ['http_code' => 200]),
            new MockResponse($this->fixture('le-studio-tech-detail.html'), ['http_code' => 200]),
        ]);
        $provider = $this->provider($http, true, 1, 1);

        self::assertSame('le-studio-tech', $provider->code());
        self::assertSame('Le Studio Tech', $provider->name());
        self::assertSame(ConnectorMode::SCRAPING_HTTP, $provider->mode());
        self::assertSame('le-studio-tech-html-v1', $provider->parserVersion());
        self::assertTrue($provider->policy()->respectsRobotsTxt);
        self::assertSame(60, $provider->policy()->maxRequestsPerSync);
        self::assertSame(240, $provider->policy()->dailyQuota);

        $offers = $provider->search(['Développeur Symfony'], ['PHP', 'Symfony']);

        self::assertCount(2, $offers);
        self::assertSame('Développeur PHP Symfony Senior', $offers[0]['title']);
        self::assertTrue($offers[0]['rawData']['detailEnriched']);
        self::assertStringContainsString('PHP 8', $offers[0]['description']);
        self::assertSame('Développeur Java Spring', $offers[1]['title']);
        self::assertFalse($offers[1]['rawData']['detailEnriched']);
        self::assertSame(3, $http->getRequestsCount());
    }

    public function testSmokeTestReadsOneListingAndAtMostOneDetailWithoutPersistence(): void
    {
        $http = new MockHttpClient([
            new MockResponse("User-agent: *\nAllow: /freelances/missions\n", ['http_code' => 200]),
            new MockResponse($this->fixture('le-studio-tech-missions.html'), [
                'http_code' => 200,
                'response_headers' => ['content-type: text/html; charset=UTF-8'],
            ]),
            new MockResponse($this->fixture('le-studio-tech-detail.html'), ['http_code' => 200]),
        ]);
        $provider = $this->provider($http, true, 8, 20);

        $result = $provider->smokeTest();

        self::assertSame('PASS', $result['result']);
        self::assertSame('Le Studio Tech', $result['source']);
        self::assertSame('SCRAPING_HTTP', $result['mode']);
        self::assertSame('le-studio-tech-html-v1', $result['parserVersion']);
        self::assertSame(200, $result['statusCode']);
        self::assertSame('app.lestudiotech.com', $result['finalHost']);
        self::assertSame(2, $result['candidateCount']);
        self::assertTrue($result['detailChecked']);
        self::assertSame(200, $result['detailStatusCode']);
        self::assertTrue($result['detailExtracted']);
        self::assertGreaterThanOrEqual(0, $result['durationMs']);
        self::assertSame(3, $http->getRequestsCount());
    }

    public function testSmokeTestWarnsWhenNoCurrentMissionIsExtracted(): void
    {
        $http = new MockHttpClient([
            new MockResponse("User-agent: *\nAllow: /freelances/missions\n", ['http_code' => 200]),
            new MockResponse('<html><body><main><h1>Aucune mission actuellement</h1></main></body></html>', ['http_code' => 200]),
        ]);
        $provider = $this->provider($http, true, 8, 20);

        $result = $provider->smokeTest();

        self::assertSame('WARN', $result['result']);
        self::assertSame(0, $result['candidateCount']);
        self::assertFalse($result['detailChecked']);
        self::assertNull($result['detailStatusCode']);
        self::assertNull($result['detailExtracted']);
        self::assertSame(2, $http->getRequestsCount());
    }

    public function testDisabledConnectorNeverCallsNetwork(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new \LogicException('Le réseau ne doit pas être appelé quand le connecteur est désactivé.');
        });
        $provider = $this->provider($http, false, 1, 1);

        self::assertFalse($provider->isConfigured());
        self::assertSame([], $provider->search(['Symfony'], ['PHP']));
        self::assertSame(0, $http->getRequestsCount());
    }

    private function provider(MockHttpClient $http, bool $enabled, int $pages, int $maxDetails): LeStudioTechJobProvider
    {
        return new LeStudioTechJobProvider(
            new ControlledHttpScrapingClient(
                $http,
                new HttpScrapingStateStore($this->directory.'/state'),
                new RobotsTxtGuard($http, $this->directory.'/robots'),
            ),
            new LeStudioTechMissionParser(),
            $enabled,
            $pages,
            $maxDetails,
        );
    }

    private function fixture(string $name): string
    {
        $content = file_get_contents(dirname(__DIR__).'/Fixtures/html/'.$name);
        self::assertIsString($content);

        return $content;
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
