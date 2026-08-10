<?php

declare(strict_types=1);

namespace App\JobCatalog\Application;

use App\Entity\JobOffer;
use App\Service\JobDescriptionContaminationDetector;

final class ContaminatedJobDescriptionRepairer
{
    private JobDescriptionContaminationDetector $detector;

    public function __construct(?JobDescriptionContaminationDetector $detector = null)
    {
        $this->detector = $detector ?? new JobDescriptionContaminationDetector();
    }

    /** @param array<string, mixed> $payload */
    public function repair(JobOffer $job, array $payload, string $sourceCode): bool
    {
        if (strtolower(trim($sourceCode)) !== 'gmail') {
            return false;
        }

        $currentDescription = trim($job->getDescription());
        $candidateDescription = trim((string) ($payload['description'] ?? ''));
        if ($currentDescription === '' || $candidateDescription === '' || $currentDescription === $candidateDescription) {
            return false;
        }
        if (!$this->detector->isMultiOfferDigest($currentDescription)) {
            return false;
        }
        if ($this->detector->isMultiOfferDigest($candidateDescription)) {
            return false;
        }

        $job->fill(['description' => $candidateDescription]);

        return true;
    }
}
