<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\JobOffer;
use App\Entity\UserSettings;

final readonly class AiOfferIntakeFilter
{
    public const MINIMUM_REJECTION_CONFIDENCE = 0.85;

    public function __construct(
        private AiMatchingConfigurationStore $configuration,
        private AiJobMatchAnalyzerInterface $analyzer,
    ) {
    }

    /**
     * Returns the AI analysis only when the offer is safe to discard before persistence.
     * A null result means "keep it" so provider errors, quota exhaustion and ambiguity fail open.
     */
    public function rejection(JobOffer $job, UserSettings $settings): ?AiJobMatchAnalysis
    {
        $configuration = $this->configuration->effective();
        if (!$configuration['enabled'] || trim($configuration['apiKey']) === '') {
            return null;
        }

        $analysis = $this->analyzer->analyze($job, $settings);
        if ($analysis === null) {
            return null;
        }

        if ($analysis->decision !== 'NO_MATCH' || $analysis->confidence < self::MINIMUM_REJECTION_CONFIDENCE) {
            return null;
        }

        $hasConcreteMismatchEvidence = $analysis->score < $settings->getMatchingThreshold()
            || $analysis->missingMustHaves !== []
            || $analysis->conflicts !== [];

        return $hasConcreteMismatchEvidence ? $analysis : null;
    }
}
