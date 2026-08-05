<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingException;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingRequest;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingStateStore;
use App\JobDiscovery\Infrastructure\Scraping\Http\RobotsTxtGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ControlledHttpScrapingClientTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-http-scraping-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testBlockedPolicyNeverCallsNetwork(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new \LogicException('Le réseau ne doit pas être appelé.');
        });
        $client = $this->client($http);

        $this->expectException(HttpScrapingException::class);
        $this->expectExceptionMessage('interdit la collecte automatisée');
        $client->fetch(new HttpScrapingRequest(
            'blocked-source',
            'https://jobs.example.test/offers',
            new ConnectorPolicy(ConnectorComplianceStatus::UNDER_REVIEW),
        ));
    }

    public function testRetriesThenReusesConditionalHttpCache(): void
    {
        $http = new MockHttpClient([
            new MockResponse('temporary', ['http_code' => 503]),
            new MockResponse('<html>offre</html>', [
                'http_code' => 200,
                'response_headers' => [
                    'content-type: text/html; charset=UTF-8',
                    'etag: "catalog-v1"',
                    'last-modified: Tue, 05 Aug 2026 10:00:00 GMT',
                ],
            ]),
            new MockResponse('', ['http_code' => 304]),
        ]);
        $client = $this->client($http);
        $request = new HttpScrapingRequest(
            'pilot-source',
            'https://jobs.example.test/offers',
            $this->allowedPolicy(maxRequestsPerSync: 5, dailyQuota: 10),
            maxRetries: 1,
            initialBackoffMilliseconds: 0,
        );

        $first = $client->fetch($request);
        self::assertSame(200, $first->statusCode);
        self::assertSame('<html>offre</html>', $first->body);
        self::assertSame(2, $first->attempts);
        self::assertFalse($first->fromCache);

        $second = $client->fetch($request);
        self::assertSame(200, $second->statusCode);
        self::assertSame('<html>offre</html>', $second->body);
        self::assertSame(1, $second->attempts);
        self::assertTrue($second->fromCache);
    }

    public function testDailyQuotaIsPersistedAcrossClientInstances(): void
    {
        $policy = $this->allowedPolicy(maxRequestsPerSync: 5, dailyQuota: 1);
        $firstClient = $this->client(new MockHttpClient(new MockResponse('ok', ['http_code' => 200])));
        $firstClient->fetch(new HttpScrapingRequest(
            'quota-source',
            'https://jobs.example.test/offers',
            $policy,
            maxRetries: 0,
        ));

        $secondClient = $this->client(new MockHttpClient(static function (): never {
            throw new \LogicException('Le quota doit bloquer avant l’appel réseau.');
        }));

        $this->expectException(HttpScrapingException::class);
        $this->expectExceptionMessage('quota journalier');
        $secondClient->fetch(new HttpScrapingRequest(
            'quota-source',
            'https://jobs.example.test/offers?page=2',
            $policy,
            maxRetries: 0,
        ));
    }

    public function testAccessDeniedResponseOpensCircuitBreaker(): void
    {
        $policy = $this->allowedPolicy(maxRequestsPerSync: 5, dailyQuota: 10);
        $firstClient = $this->client(new MockHttpClient(new MockResponse('forbidden', ['http_code' => 403])));

        try {
            $firstClient->fetch(new HttpScrapingRequest(
                'denied-source',
                'https://jobs.example.test/private',
                $policy,
                maxRetries: 0,
            ));
            self::fail('Une réponse 403 doit faire échouer la collecte.');
        } catch (HttpScrapingException $exception) {
            self::assertStringContainsString('statut HTTP 403', $exception->getMessage());
        }

        $secondClient = $this->client(new MockHttpClient(static function (): never {
            throw new \LogicException('Le circuit breaker doit bloquer avant l’appel réseau.');
        }));

        $this->expectException(HttpScrapingException::class);
        $this->expectExceptionMessage('circuit breaker');
        $secondClient->fetch(new HttpScrapingRequest(
            'denied-source',
            'https://jobs.example.test/another-page',
            $policy,
            maxRetries: 0,
        ));
    }

    public function testRobotsTxtCanBlockAPath(): void
    {
        $http = new MockHttpClient(new MockResponse("User-agent: *\nDisallow: /offers/private\n", [
            'http_code' => 200,
            'response_headers' => ['content-type: text/plain'],
        ]));
        $client = $this->client($http);

        $this->expectException(HttpScrapingException::class);
        $this->expectExceptionMessage('robots.txt interdit');
        $client->fetch(new HttpScrapingRequest(
            'robots-source',
            'https://jobs.example.test/offers/private/123',
            $this->allowedPolicy(respectsRobotsTxt: true),
            maxRetries: 0,
        ));
    }

    public function testRedirectToPrivateAddressIsRejected(): void
    {
        $http = new MockHttpClient(new MockResponse('', [
            'http_code' => 302,
            'response_headers' => ['location: http://127.0.0.1/internal'],
        ]));
        $client = $this->client($http);

        $this->expectException(HttpScrapingException::class);
        $this->expectExceptionMessage('IP privées ou réservées');
        $client->fetch(new HttpScrapingRequest(
            'redirect-source',
            'https://jobs.example.test/offers',
            $this->allowedPolicy(),
            maxRetries: 0,
        ));
    }

    private function client(MockHttpClient $http): ControlledHttpScrapingClient
    {
        return new ControlledHttpScrapingClient(
            $http,
            new HttpScrapingStateStore($this->directory.'/state'),
            new RobotsTxtGuard($http, $this->directory.'/robots'),
        );
    }

    private function allowedPolicy(
        int $maxRequestsPerSync = 10,
        int $dailyQuota = 100,
        bool $respectsRobotsTxt = false,
    ): ConnectorPolicy {
        return new ConnectorPolicy(
            ConnectorComplianceStatus::ALLOWED,
            new \DateTimeImmutable('2026-08-05'),
            'Source pilote autorisée pour les tests locaux.',
            $maxRequestsPerSync,
            $dailyQuota,
            0,
            $respectsRobotsTxt,
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
