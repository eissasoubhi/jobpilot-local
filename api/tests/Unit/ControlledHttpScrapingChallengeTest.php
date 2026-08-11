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

final class ControlledHttpScrapingChallengeTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-http-challenge-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testChallengePageIsRejectedNotCachedAndOpensCircuitBreaker(): void
    {
        $http = new MockHttpClient(new MockResponse(
            '<html><script src="/cdn-cgi/challenge-platform/h/g/orchestrate/chl_page/v1"></script></html>',
            ['http_code' => 200, 'response_headers' => ['content-type: text/html']],
        ));
        $stateStore = new HttpScrapingStateStore($this->directory.'/state');
        $client = new ControlledHttpScrapingClient(
            $http,
            $stateStore,
            new RobotsTxtGuard($http, $this->directory.'/robots'),
        );
        $request = new HttpScrapingRequest(
            'challenge-source',
            'https://jobs.example.test/offers',
            $this->allowedPolicy(),
            maxRetries: 0,
        );

        try {
            $client->fetch($request);
            self::fail('Une page de challenge HTTP 200 ne doit jamais être traitée comme une page d’offres.');
        } catch (HttpScrapingException $exception) {
            self::assertStringContainsString('Protection anti-automatisation détectée', $exception->getMessage());
            self::assertStringContainsString('aucun contournement automatique', $exception->getMessage());
        }

        $state = $stateStore->read('challenge-source');
        self::assertSame([], $state['cache']);
        self::assertSame(1, $state['consecutiveFailures']);
        self::assertIsString($state['circuitOpenUntil']);

        $blockedHttp = new MockHttpClient(static function (): never {
            throw new \LogicException('Le circuit breaker doit bloquer avant un nouvel appel réseau.');
        });
        $blockedClient = new ControlledHttpScrapingClient(
            $blockedHttp,
            $stateStore,
            new RobotsTxtGuard($blockedHttp, $this->directory.'/robots-2'),
        );

        $this->expectException(HttpScrapingException::class);
        $this->expectExceptionMessage('circuit breaker');
        $blockedClient->fetch($request);
    }

    private function allowedPolicy(): ConnectorPolicy
    {
        return new ConnectorPolicy(
            ConnectorComplianceStatus::ALLOWED,
            new \DateTimeImmutable('2026-08-11'),
            'Source autorisée pour ce test.',
            maxRequestsPerSync: 5,
            dailyQuota: 10,
            minimumDelayMilliseconds: 0,
            respectsRobotsTxt: false,
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
