<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;

interface ApplicationQuestionAiSuggesterInterface
{
    /**
     * @return array{canAnswer: bool, answer: string, confidence: float, usedFacts: list<string>, model: string}|null
     */
    public function suggest(
        JobOffer $job,
        CandidateProfile $profile,
        string $question,
        string $language,
        int $maxLength,
    ): ?array;
}
