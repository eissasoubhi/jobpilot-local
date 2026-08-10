<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Infrastructure\Browser\BrowserWorkerConfiguration;
use App\JobDiscovery\Infrastructure\Browser\HttpBrowserRenderClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpBrowserRenderClientTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['BROWSER_WORKER_URL'], $_ENV['JOBPILOT_BROWSER_WORKER_TOKEN']);
        putenv('BROWSER_WORKER_URL');
        putenv('JOBPILOT_BROWSER_WORKER_TOKEN');
    }

    public function testRendersThroughConfiguredWorkerAndRevalidatesResponse(): void
    {
        $this->configure();
        $http = new MockHttpClient(new MockResponse(json_encode([
            'requestedUrl' => 'https://jobs.example.test/offres',
            'finalUrl' => 'https://jobs.example.test/offres?page=1',
            'statusCode' => 200,
            'title' => 'Jobs',
            'html' => '<html><body><h1>Offres</h1></body></html>',
            'htmlBytes' => strlen('<html><body><h1>Offres</h1></body></html>'),
            'allowedRequests' => 12,
            'blockedRequests' => 5,
        ], JSON_THROW_ON_ERROR), ['http_code' => 200]));
        $client = new HttpBrowserRenderClient($http, new BrowserWorkerConfiguration());

        $result = $client->render(
            'custom-scraper-42',
            'https://jobs.example.test/offres',
            'jobs.example.test',
            true,
            true,
        );

        self::assertTrue($client->isConfigured());
        self::assertSame('https://jobs.example.test/offres?page=1', $result['finalUrl']);
        self::assertSame(200, $result['statusCode']);
        self::assertSame(12, $result['allowedRequests']);
        self::assertSame(5, $result['blockedRequests']);
        self::assertSame(1, $http->getRequestsCount());
    }

    public function testMissingWorkerConfigurationBlocksBeforeNetwork(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new \LogicException('Le réseau ne doit pas être appelé.');
        });
        $client = new HttpBrowserRenderClient($http, new BrowserWorkerConfiguration());

        self::assertFalse($client->isConfigured());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pas configuré');
        $client->render('custom-scraper-1', 'https://jobs.example.test/jobs', 'jobs.example.test', true, true);
    }

    public function testCallerMustConfirmAuthorizationAndRobotsBeforeNetwork(): void
    {
        $this->configure();
        $http = new MockHttpClient(static function (): never {
            throw new \LogicException('Le réseau ne doit pas être appelé.');
        });
        $client = new HttpBrowserRenderClient($http, new BrowserWorkerConfiguration());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('robots.txt');
        $client->render('custom-scraper-1', 'https://jobs.example.test/jobs', 'jobs.example.test', true, false);
    }

    public function testRejectsCrossDomainFinalUrlReturnedByWorker(): void
    {
        $this->configure();
        $http = new MockHttpClient(new MockResponse(json_encode([
            'finalUrl' => 'https://login.example.net/auth',
            'statusCode' => 200,
            'html' => '<html><body>Login</body></html>',
            'htmlBytes' => strlen('<html><body>Login</body></html>'),
        ], JSON_THROW_ON_ERROR), ['http_code' => 200]));
        $client = new HttpBrowserRenderClient($http, new BrowserWorkerConfiguration());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('domaine HTTPS autorisé');
        $client->render('custom-scraper-1', 'https://jobs.example.test/jobs', 'jobs.example.test', true, true);
    }

    public function testRejectsIncoherentRenderedHtmlSize(): void
    {
        $this->configure();
        $http = new MockHttpClient(new MockResponse(json_encode([
            'finalUrl' => 'https://jobs.example.test/jobs',
            'statusCode' => 200,
            'html' => '<html><body>Jobs</body></html>',
            'htmlBytes' => 99999,
        ], JSON_THROW_ON_ERROR), ['http_code' => 200]));
        $client = new HttpBrowserRenderClient($http, new BrowserWorkerConfiguration());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('incohérente');
        $client->render('custom-scraper-1', 'https://jobs.example.test/jobs', 'jobs.example.test', true, true);
    }

    private function configure(): void
    {
        $_ENV['BROWSER_WORKER_URL'] = 'http://browser-worker:3100';
        $_ENV['JOBPILOT_BROWSER_WORKER_TOKEN'] = 'test-browser-worker-token-123456789';
    }
}
