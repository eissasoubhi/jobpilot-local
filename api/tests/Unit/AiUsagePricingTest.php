<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Ai\AiUsagePricing;
use PHPUnit\Framework\TestCase;

final class AiUsagePricingTest extends TestCase
{
    public function testPaidGeminiEstimateSeparatesCachedInputAndThoughtTokens(): void
    {
        $pricing = new AiUsagePricing();

        $estimate = $pricing->estimate('gemini', 'gemini-3.5-flash-lite', [
            'total_input_tokens' => 1_000_000,
            'total_cached_tokens' => 400_000,
            'total_output_tokens' => 100_000,
            'total_thought_tokens' => 20_000,
        ]);

        self::assertTrue($estimate['supported']);
        self::assertSame(600_000, $estimate['uncachedInputTokens']);
        self::assertSame(400_000, $estimate['cachedInputTokens']);
        self::assertSame(120_000, $estimate['billableOutputTokens']);
        self::assertEqualsWithDelta(0.492, $estimate['estimatedCostUsd'], 0.0000001);
    }

    public function testFreeTierReturnsZeroAndUnknownModelsRemainUnpriced(): void
    {
        $pricing = new AiUsagePricing();

        $free = $pricing->estimate('gemini', 'gemini-3.5-flash-lite', ['total_input_tokens' => 5000], 'free');
        self::assertTrue($free['supported']);
        self::assertSame(0.0, $free['estimatedCostUsd']);

        $unknown = $pricing->estimate('gemini', 'future-model', ['total_input_tokens' => 5000]);
        self::assertFalse($unknown['supported']);
        self::assertNull($unknown['estimatedCostUsd']);
    }
}
