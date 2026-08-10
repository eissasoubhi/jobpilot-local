<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Application\BrowserRenderClientInterface;
use App\JobDiscovery\Application\CustomScraperBrowserRenderCoordinator;
use App\JobDiscovery\Application\CustomScraperBrowserRenderPolicy;
use PHPUnit\Framework\TestCase;

final class CustomScraperBrowserRenderCoordinatorTest extends TestCase
{
    public function testCallsWorkerOnlyWhenPolicyAllowsRendering(): void
    {
        $client = new class implements BrowserRenderClientInterface {
            public int $calls = 0;
            public function isConfigured(): bool { return true; }
            public function render(string $sourceCode, string $url, string $allowedDomain, bool $authorizationApproved, bool $robotsApproved): array
            {
                ++$this->calls;
                return [
                    'requestedUrl' => $url,
                    'finalUrl' => $url,
                    'statusCode' => 200,
                    'title' => 'Jobs',
                    'html' => '<html><body>Rendered jobs</body></html>',
                    'htmlBytes' => 39,
                    'allowedRequests' => 10,
                    'blockedRequests' => 4,
                ];
            }
        };
        $coordinator = new CustomScraperBrowserRenderCoordinator($client, new CustomScraperBrowserRenderPolicy());

        $result = $coordinator->renderIfAllowed(
            'custom-scraper-42',
            'https://jobs.example.test/jobs',
            'jobs.example.test',
            'AUTO',
            'BROWSER',
            true,
            true,
        );

        self::assertTrue($result['rendered']);
        self::assertIsArray($result['result']);
        self::assertSame(1, $client->calls);
    }

    public function testRefusedPolicyDoesNotCallWorker(): void
    {
        $client = new class implements BrowserRenderClientInterface {
            public int $calls = 0;
            public function isConfigured(): bool { return true; }
            public function render(string $sourceCode, string $url, string $allowedDomain, bool $authorizationApproved, bool $robotsApproved): array
            {
                ++$this->calls;
                return [];
            }
        };
        $coordinator = new CustomScraperBrowserRenderCoordinator($client, new CustomScraperBrowserRenderPolicy());

        $result = $coordinator->renderIfAllowed(
            'custom-scraper-42',
            'https://jobs.example.test/jobs',
            'jobs.example.test',
            'HTTP',
            'BROWSER',
            true,
            true,
        );

        self::assertFalse($result['rendered']);
        self::assertNull($result['result']);
        self::assertSame(0, $client->calls);
    }
}
