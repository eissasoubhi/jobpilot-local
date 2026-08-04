<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;
use App\Entity\CvDocument;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;

final class ApplicationCvRepairService
{
    public function __construct(
        private CvSelector $cvSelector,
        private EntityManagerInterface $em,
    ) {}

    public function resolveForJob(JobOffer $job): ?CvDocument
    {
        $recommended = $job->getRecommendedCv();
        if ($recommended !== null && $recommended->isActive()) {
            return $recommended;
        }

        return $this->cvSelector->select(
            $job->getLanguage(),
            $job->getTitle().' '.$job->getDescription(),
        );
    }

    public function repair(Application $application): bool
    {
        if ($application->getCvDocument() !== null) {
            return false;
        }

        if (in_array($application->getStatus(), ['SUBMITTED', 'SUBMISSION_PENDING'], true)) {
            return false;
        }

        $cv = $this->resolveForJob($application->getJobOffer());
        if ($cv === null) {
            return $application->markMissingCv();
        }

        $application->attachCv($cv);

        return true;
    }

    /**
     * @param iterable<Application> $applications
     */
    public function repairAll(iterable $applications): int
    {
        $repaired = 0;
        $changed = false;

        foreach ($applications as $application) {
            $before = $application->getStatus();
            if ($this->repair($application)) {
                ++$repaired;
                $changed = true;
                continue;
            }

            if ($application->getStatus() !== $before) {
                $changed = true;
            }
        }

        if ($changed) {
            $this->em->flush();
        }

        return $repaired;
    }
}
