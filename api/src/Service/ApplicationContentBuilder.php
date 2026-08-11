<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;

final class ApplicationContentBuilder
{
    public function __construct(
        private ApplicationMessageBuilder $messageBuilder,
        private CoverLetterRequirementDetector $coverLetterRequirementDetector,
        private ?GroundedCoverLetterBuilder $coverLetterBuilder = null,
    ) {
    }

    /**
     * @param list<string> $profileSkills
     * @return array{message: string, coverLetter: string, coverLetterRequired: bool}
     */
    public function build(JobOffer $job, CandidateProfile $profile, array $profileSkills = []): array
    {
        $content = $this->messageBuilder->build($job, $profile);
        $coverLetterRequired = $this->coverLetterRequirementDetector->isRequired(
            $job->getTitle().' '.$job->getDescription(),
        );
        $coverLetterBuilder = $this->coverLetterBuilder ?? new GroundedCoverLetterBuilder();

        return [
            'message' => $content['message'],
            'coverLetter' => $coverLetterBuilder->build($job, $profile, $profileSkills),
            'coverLetterRequired' => $coverLetterRequired,
        ];
    }
}
