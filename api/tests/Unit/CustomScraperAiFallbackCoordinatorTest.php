<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Application\CustomScraperAiExtractorInterface;
use App\JobDiscovery\Application\CustomScraperAiFallbackCoordinator;
use App\JobDiscovery\Application\CustomScraperAiFallbackPolicy;
use PHPUnit\Framework\TestCase;

final class CustomScraperAiFallbackCoordinatorTest extends TestCase
{
    public function testCallsGroundedExtractorWhenPolicyAllowsFallback(): void
    {
        $extractor = new class implements CustomScraperAiExtractorInterface {
            public int $calls = 0;

            public function extract(string $html, string $pageUrl, string $sourceName): array
            {
                ++$this->calls;

                return [[
                    'title' => 'Senior Symfony Developer',
                    'sourceUrl' => 'https://jobs.example.test/careers/42',
                    'externalId' => 'link-42',
                    'rawData' => ['extractionMethod' => 'AI_GROUNDED_LINK'],
                ]];
            }
        };
        $coordinator = new CustomScraperAiFallbackCoordinator($extractor, new CustomScraperAiFallbackPolicy());

        $result = $coordinator->extractIfNeeded(
            '<html><body>Nos offres Symfony</body></html>',
            'https://jobs.example.test/careers',
            'Example Jobs',
            $this->analysis(),
            [],
            0,
            2,
        );

        self::assertTrue($result['attempted']);
        self::assertCount(1, $result['candidates']);
        self::assertSame(1, $extractor->calls);
    }

    public function testPreservesDeterministicCandidatesWithoutCallingAi(): void
    {
        $extractor = new class implements CustomScraperAiExtractorInterface {
            public int $calls = 0;

            public function extract(string $html, string $pageUrl, string $sourceName): array
            {
                ++$this->calls;

                return [];
            }
        };
        $coordinator = new CustomScraperAiFallbackCoordinator($extractor, new CustomScraperAiFallbackPolicy());
        $deterministic = [[
            'title' => 'PHP Developer',
            'sourceUrl' => 'https://jobs.example.test/jobs/php',
        ]];

        $result = $coordinator->extractIfNeeded(
            '<html><body>Nos offres PHP</body></html>',
            'https://jobs.example.test/jobs',
            'Example Jobs',
            $this->analysis(),
            $deterministic,
            0,
            2,
        );

        self::assertFalse($result['attempted']);
        self::assertSame($deterministic, $result['candidates']);
        self::assertSame(0, $extractor->calls);
    }

    public function testAttemptReturningNothingDoesNotInventCandidates(): void
    {
        $extractor = new class implements CustomScraperAiExtractorInterface {
            public int $calls = 0;

            public function extract(string $html, string $pageUrl, string $sourceName): array
            {
                ++$this->calls;

                return [];
            }
        };
        $coordinator = new CustomScraperAiFallbackCoordinator($extractor, new CustomScraperAiFallbackPolicy());

        $result = $coordinator->extractIfNeeded(
            '<html><body>Nos offres PHP</body></html>',
            'https://jobs.example.test/jobs',
            'Example Jobs',
            $this->analysis(),
            [],
            1,
            2,
        );

        self::assertTrue($result['attempted']);
        self::assertSame([], $result['candidates']);
        self::assertSame(1, $extractor->calls);
    }

    /** @return array<string, mixed> */
    private function analysis(): array
    {
        return [
            'recommendedMode' => 'HTTP',
            'signals' => [
                'visibleTextCharacters' => 1_200,
                'jobKeywordHits' => 3,
                'jobLikeLinks' => 0,
            ],
        ];
    }
}
