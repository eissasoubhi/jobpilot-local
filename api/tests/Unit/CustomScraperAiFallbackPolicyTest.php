<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Application\CustomScraperAiFallbackPolicy;
use PHPUnit\Framework\TestCase;

final class CustomScraperAiFallbackPolicyTest extends TestCase
{
    public function testAllowsFallbackOnlyWhenDeterministicHttpExtractionMissesAJobLikePage(): void
    {
        $analysis = $this->analysis('HTTP', 1_500, 3, 0);

        self::assertTrue($this->policy()->shouldAttempt($analysis, [], 0, 2));
    }

    public function testNeverUsesFallbackWhenDeterministicCandidatesAlreadyExist(): void
    {
        $analysis = $this->analysis('HTTP', 1_500, 3, 2);

        self::assertFalse($this->policy()->shouldAttempt($analysis, [['title' => 'Symfony Developer']], 0, 2));
    }

    public function testNeverUsesFallbackForBrowserRecommendedPages(): void
    {
        self::assertFalse($this->policy()->shouldAttempt(
            $this->analysis('BROWSER', 1_500, 4, 0),
            [],
            0,
            2,
        ));
    }

    public function testRequiresMeaningfulVisibleJobSignals(): void
    {
        self::assertFalse($this->policy()->shouldAttempt($this->analysis('HTTP', 200, 4, 0), [], 0, 2));
        self::assertFalse($this->policy()->shouldAttempt($this->analysis('HTTP', 1_500, 0, 0), [], 0, 2));
        self::assertTrue($this->policy()->shouldAttempt($this->analysis('HTTP', 1_500, 0, 2), [], 0, 2));
    }

    public function testHonorsZeroPreviewBudgetAndPerSyncAttemptCap(): void
    {
        $analysis = $this->analysis('HTTP', 1_500, 2, 0);

        self::assertFalse($this->policy()->shouldAttempt($analysis, [], 0, 0));
        self::assertTrue($this->policy()->shouldAttempt($analysis, [], 1, 2));
        self::assertFalse($this->policy()->shouldAttempt($analysis, [], 2, 2));
    }

    /** @return array<string, mixed> */
    private function analysis(string $mode, int $visibleText, int $keywords, int $jobLinks): array
    {
        return [
            'recommendedMode' => $mode,
            'signals' => [
                'visibleTextCharacters' => $visibleText,
                'jobKeywordHits' => $keywords,
                'jobLikeLinks' => $jobLinks,
            ],
        ];
    }

    private function policy(): CustomScraperAiFallbackPolicy
    {
        return new CustomScraperAiFallbackPolicy();
    }
}
