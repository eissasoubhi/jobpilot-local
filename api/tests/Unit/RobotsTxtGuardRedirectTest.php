<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingException;
use App\JobDiscovery\Infrastructure\Scraping\Http\RobotsTxtGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RobotsTxtGuardRedirectTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-robots-redirects-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testCanonicalRedirectEndsInUsableRobotsFile(): void
    {
        $guard = $this->guard([
            new MockResponse('', [
                'http_code' => 301,
                'response_headers' => ['location: /robots-canonical.txt'],
            ]),
            new MockResponse("User-agent: *\nAllow: /\n", ['http_code' => 200]),
        ]);

        $result = $guard->assertAllowed('https://jobs.example.test/offres', 'JobPilot/0.2');

        self::assertSame('https://jobs.example.test/robots.txt', $result->requestedUrl);
        self::assertSame('https://jobs.example.test/robots-canonical.txt', $result->finalUrl);
        self::assertSame(200, $result->statusCode);
        self::assertSame(1, $result->redirects);
        self::assertFalse($result->fromCache);
    }

    public function testCanonicalWwwToApexRedirectIsAllowed(): void
    {
        $guard = $this->guard([
            new MockResponse('', [
                'http_code' => 301,
                'response_headers' => ['location: https://jobs.example.test/robots.txt'],
            ]),
            new MockResponse("User-agent: *\nAllow: /\n", ['http_code' => 200]),
        ]);

        $result = $guard->assertAllowed('https://www.jobs.example.test/offres', 'JobPilot/0.2');

        self::assertSame('https://jobs.example.test/robots.txt', $result->finalUrl);
        self::assertSame(1, $result->redirects);
    }

    public function testMultipleSafeRedirectsAreFollowed(): void
    {
        $guard = $this->guard([
            new MockResponse('', [
                'http_code' => 301,
                'response_headers' => ['location: /robots-step-1.txt'],
            ]),
            new MockResponse('', [
                'http_code' => 302,
                'response_headers' => ['location: /robots-step-2.txt'],
            ]),
            new MockResponse("User-agent: *\nDisallow:\n", ['http_code' => 200]),
        ]);

        $result = $guard->assertAllowed('https://jobs.example.test/offres', 'JobPilot/0.2');

        self::assertSame(200, $result->statusCode);
        self::assertSame(2, $result->redirects);
        self::assertSame('https://jobs.example.test/robots-step-2.txt', $result->finalUrl);
    }

    public function testRedirectLoopIsRejected(): void
    {
        $guard = $this->guard([
            new MockResponse('', [
                'http_code' => 301,
                'response_headers' => ['location: /robots-a.txt'],
            ]),
            new MockResponse('', [
                'http_code' => 302,
                'response_headers' => ['location: /robots.txt'],
            ]),
        ]);

        $this->expectException(HttpScrapingException::class);
        $this->expectExceptionMessage('Boucle de redirection');
        $guard->assertAllowed('https://jobs.example.test/offres', 'JobPilot/0.2');
    }

    public function testMoreThanFiveRedirectsAreRejected(): void
    {
        $guard = $this->guard([
            $this->redirect('/robots-1.txt'),
            $this->redirect('/robots-2.txt'),
            $this->redirect('/robots-3.txt'),
            $this->redirect('/robots-4.txt'),
            $this->redirect('/robots-5.txt'),
            $this->redirect('/robots-6.txt'),
        ]);

        $this->expectException(HttpScrapingException::class);
        $this->expectExceptionMessage('limite de 5 redirections');
        $guard->assertAllowed('https://jobs.example.test/offres', 'JobPilot/0.2');
    }

    public function testHttpsToHttpDowngradeIsRejected(): void
    {
        $guard = $this->guard([
            new MockResponse('', [
                'http_code' => 301,
                'response_headers' => ['location: http://jobs.example.test/robots.txt'],
            ]),
        ]);

        $this->expectException(HttpScrapingException::class);
        $this->expectExceptionMessage('HTTPS vers HTTP');
        $guard->assertAllowed('https://jobs.example.test/offres', 'JobPilot/0.2');
    }

    public function testRedirectToPrivateIpIsRejected(): void
    {
        $guard = $this->guard([
            new MockResponse('', [
                'http_code' => 302,
                'response_headers' => ['location: https://127.0.0.1/robots.txt'],
            ]),
        ]);

        $this->expectException(HttpScrapingException::class);
        $this->expectExceptionMessage('IP privées ou réservées');
        $guard->assertAllowed('https://jobs.example.test/offres', 'JobPilot/0.2');
    }

    public function testRedirectToUnrelatedDomainIsRejected(): void
    {
        $guard = $this->guard([
            new MockResponse('', [
                'http_code' => 301,
                'response_headers' => ['location: https://other.example.test/robots.txt'],
            ]),
        ]);

        $this->expectException(HttpScrapingException::class);
        $this->expectExceptionMessage('domaine différent non autorisé');
        $guard->assertAllowed('https://jobs.example.test/offres', 'JobPilot/0.2');
    }

    public function testCachedRedirectMetadataIsReturnedWithoutNewNetworkCall(): void
    {
        $http = new MockHttpClient([
            new MockResponse('', [
                'http_code' => 301,
                'response_headers' => ['location: /robots-final.txt'],
            ]),
            new MockResponse("User-agent: *\nAllow: /\n", ['http_code' => 200]),
        ]);
        $guard = new RobotsTxtGuard($http, $this->directory);

        $first = $guard->assertAllowed('https://jobs.example.test/offres', 'JobPilot/0.2');
        $second = $guard->assertAllowed('https://jobs.example.test/autre-offre', 'JobPilot/0.2');

        self::assertFalse($first->fromCache);
        self::assertTrue($second->fromCache);
        self::assertSame($first->finalUrl, $second->finalUrl);
        self::assertSame($first->redirects, $second->redirects);
    }

    /** @param list<MockResponse> $responses */
    private function guard(array $responses): RobotsTxtGuard
    {
        return new RobotsTxtGuard(new MockHttpClient($responses), $this->directory);
    }

    private function redirect(string $location): MockResponse
    {
        return new MockResponse('', [
            'http_code' => 301,
            'response_headers' => ['location: '.$location],
        ]);
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
