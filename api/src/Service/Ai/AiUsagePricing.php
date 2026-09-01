<?php

declare(strict_types=1);

namespace App\Service\Ai;

final class AiUsagePricing
{
    private const SOURCE_URL = 'https://ai.google.dev/gemini-api/docs/pricing';
    private const VERSION = 'google-ai-pricing-2026-09-01';

    /**
     * Prices are USD per 1M tokens for Gemini Developer API Standard paid tier.
     * Unknown models remain unsupported rather than guessing a cost.
     *
     * @return array{supported: bool, source: string, version: string, inputPerMillionUsd: ?float, outputPerMillionUsd: ?float, cachedInputPerMillionUsd: ?float}
     */
    public function describe(string $provider, string $model, string $billingTier = 'paid'): array
    {
        $provider = strtolower(trim($provider));
        $model = trim($model);
        $billingTier = strtolower(trim($billingTier)) === 'free' ? 'free' : 'paid';
        $prices = $provider === 'gemini' ? $this->geminiPrices($model) : null;

        if ($prices === null) {
            return [
                'supported' => false,
                'source' => self::SOURCE_URL,
                'version' => self::VERSION,
                'inputPerMillionUsd' => null,
                'outputPerMillionUsd' => null,
                'cachedInputPerMillionUsd' => null,
            ];
        }

        if ($billingTier === 'free') {
            $prices = ['input' => 0.0, 'output' => 0.0, 'cached' => 0.0];
        }

        return [
            'supported' => true,
            'source' => self::SOURCE_URL,
            'version' => self::VERSION,
            'inputPerMillionUsd' => $prices['input'],
            'outputPerMillionUsd' => $prices['output'],
            'cachedInputPerMillionUsd' => $prices['cached'],
        ];
    }

    /**
     * @param array<string, mixed> $usage
     * @return array{supported: bool, source: string, version: string, estimatedCostUsd: ?float, uncachedInputTokens: int, cachedInputTokens: int, billableOutputTokens: int}
     */
    public function estimate(string $provider, string $model, array $usage, string $billingTier = 'paid'): array
    {
        $description = $this->describe($provider, $model, $billingTier);
        $inputTokens = $this->token($usage, 'total_input_tokens');
        $cachedTokens = min($inputTokens, $this->token($usage, 'total_cached_tokens'));
        $uncachedTokens = max(0, $inputTokens - $cachedTokens);
        $outputTokens = $this->token($usage, 'total_output_tokens');
        $thoughtTokens = $this->token($usage, 'total_thought_tokens');
        $billableOutputTokens = $outputTokens + $thoughtTokens;

        $estimatedCostUsd = null;
        if ($description['supported']) {
            $estimatedCostUsd = (
                ($uncachedTokens * (float) $description['inputPerMillionUsd'])
                + ($cachedTokens * (float) $description['cachedInputPerMillionUsd'])
                + ($billableOutputTokens * (float) $description['outputPerMillionUsd'])
            ) / 1_000_000;
            $estimatedCostUsd = round($estimatedCostUsd, 10);
        }

        return [
            'supported' => $description['supported'],
            'source' => $description['source'],
            'version' => $description['version'],
            'estimatedCostUsd' => $estimatedCostUsd,
            'uncachedInputTokens' => $uncachedTokens,
            'cachedInputTokens' => $cachedTokens,
            'billableOutputTokens' => $billableOutputTokens,
        ];
    }

    /** @return array{input: float, output: float, cached: float}|null */
    private function geminiPrices(string $model): ?array
    {
        return match ($model) {
            'gemini-3.7-flash' => ['input' => 0.75, 'output' => 3.75, 'cached' => 0.075],
            'gemini-3.6-flash' => ['input' => 0.75, 'output' => 3.75, 'cached' => 0.075],
            'gemini-3.5-flash' => ['input' => 1.50, 'output' => 9.00, 'cached' => 0.15],
            'gemini-3.5-flash-lite' => ['input' => 0.30, 'output' => 2.50, 'cached' => 0.03],
            'gemini-3.1-flash-lite' => ['input' => 0.25, 'output' => 1.50, 'cached' => 0.025],
            default => null,
        };
    }

    /** @param array<string, mixed> $usage */
    private function token(array $usage, string $key): int
    {
        return is_numeric($usage[$key] ?? null) ? max(0, (int) $usage[$key]) : 0;
    }
}
