<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

final class CustomScraperAiFallbackPolicy
{
    /**
     * @param array<string, mixed> $analysis
     * @param list<array<string, mixed>> $deterministicCandidates
     */
    public function shouldAttempt(
        array $analysis,
        array $deterministicCandidates,
        int $attempts,
        int $limit,
    ): bool {
        if ($limit <= 0 || $attempts >= $limit || $deterministicCandidates !== []) {
            return false;
        }

        if (($analysis['recommendedMode'] ?? 'HTTP') !== 'HTTP') {
            return false;
        }

        $signals = is_array($analysis['signals'] ?? null) ? $analysis['signals'] : [];
        $visibleTextCharacters = max(0, (int) ($signals['visibleTextCharacters'] ?? 0));
        $jobKeywordHits = max(0, (int) ($signals['jobKeywordHits'] ?? 0));
        $jobLikeLinks = max(0, (int) ($signals['jobLikeLinks'] ?? 0));

        if ($visibleTextCharacters < 300) {
            return false;
        }

        return $jobKeywordHits > 0 || $jobLikeLinks > 0;
    }
}
