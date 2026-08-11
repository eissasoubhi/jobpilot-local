<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;
use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;

final class ApplicationPreparationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ApplicationCvRepairService $cvRepair,
        private ApplicationContentBuilder $contentBuilder,
        private ?LocalDataService $data = null,
    ) {}

    public function prepare(JobOffer $job, CandidateProfile $profile): Application
    {
        $existing = $this->em->getRepository(Application::class)->findOneBy(['jobOffer' => $job]);
        $application = $existing ?? new Application($job);
        $profileSkills = $this->data?->settings()->getSkills() ?? [];
        $content = $this->contentBuilder->build($job, $profile, $profileSkills);

        $compensation = null;
        if ($job->getProposedTjm() !== null) {
            $compensation = $job->getProposedTjm().' € HT/jour';
        } elseif ($job->getProposedSalary() !== null) {
            $compensation = number_format($job->getProposedSalary(), 0, ',', ' ').' € brut annuel (rémunération globale)';
        }

        $cv = $this->cvRepair->resolveForJob($job);
        $application->prepare(
            $cv,
            $content['message'],
            $content['coverLetter'],
            $compensation,
        );
        $job->markPrepared();
        $this->em->persist($application);
        $this->em->flush();

        return $application;
    }
}
