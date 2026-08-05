<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Infrastructure\Feed\SyndicationJobFeedParser;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingStateStore;
use App\JobDiscovery\Infrastructure\Scraping\Http\RobotsTxtGuard;
use App\Service\SymfonyJobsRssProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SymfonyJobsRssProviderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-symfony-jobs-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testOfficialFeedIsCollectedThroughTheControlledClient(): void
    {
        $feed = file_get_contents(dirname(__DIR__).'/Fixtures/feeds/symfony-jobs.rss');
        self::assertIsString($feed);

        $http = new MockHttpClient(new MockResponse($feed, [
            'http_code' => 200,
            'response_headers' => [
                'content-type: application/rss+xml; charset=UTF-8',
                'etag: "symfony-jobs-v1"',
            ],
        ]));
        $controlled = new ControlledHttpScrapingClient(
            $http,
            new HttpScrapingStateStore($this->directory.'/state'),
            new RobotsTxtGuard($http, $this->directory.'/robots'),
        );
        $provider = new SymfonyJobsRssProvider($controlled, new SyndicationJobFeedParser(), true);

        self::assertSame('symfony-jobs', $provider->code());
        self::assertSame('Symfony Jobs', $provider->name());
        self::assertSame(ConnectorMode::RSS, $provider->mode());
        self::assertSame('syndication-v1', $provider->parserVersion());
        self::assertTrue($provider->isConfigured());
        self::assertSame(ConnectorComplianceStatus::ALLOWED, $provider->policy()->complianceStatus);
        self::assertSame(4, $provider->policy()->maxRequestsPerSync);
        self::assertSame(16, $provider->policy()->dailyQuota);

        $offers = $provider->search(['Senior Symfony Developer'], ['PHP', 'Symfony']);

        self::assertCount(2, $offers);
        self::assertSame('Symfony Jobs', $offers[0]['source']);
        self::assertSame('symfony-job-123', $offers[0]['externalId']);
    }

    public function testDisabledConnectorDoesNotCallNetwork(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new \LogicException('Le réseau ne doit pas être appelé.');
        });
        $provider = new SymfonyJobsRssProvider(
            new ControlledHttpScrapingClient(
                $http,
                new HttpScrapingStateStore($this->directory.'/state'),
                new RobotsTxtGuard($http, $this->directory.'/robots'),
            ),
            new SyndicationJobFeedParser(),
            false,
        );

        self::assertFalse($provider->isConfigured());
        self::assertStringContainsString('SYMFONY_JOBS_RSS_ENABLED', (string) $provider->configurationMessage());
        self::assertSame([], $provider->search([], []));
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
