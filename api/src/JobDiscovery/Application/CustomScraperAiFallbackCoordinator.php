<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

final class CustomScraperAiFallbackCoordinator
{
    public function __construct(
        private CustomScraperAiExtractorInterface $extractor,
        private CustomScraperAiFallbackPolicy $policy,
    ) {
    }

    /**
     * @param array<string, mixed> $analysis
     * @param list<array<string, mixed>> $deterministicCandidates
     * @return array{attempted: bool, candidates: list<array<string, mixed>>}
     */
    public function extractIfNeeded(
        string $html,
        string $pageUrl,
        string $sourceName,
        array $analysis,
        array $deterministicCandidates,
        int $attempts,
        int $limit,
    ): array {
        if (!$this->policy->shouldAttempt($analysis, $deterministicCandidates, $attempts, $limit)) {
            return [
                'attempted' => false,
                'candidates' => $deterministicCandidates,
            ];
        }

        return [
            'attempted' => true,
            'candidates' => $this->extractor->extract($html, $pageUrl, $sourceName),
        ];
    }
}
