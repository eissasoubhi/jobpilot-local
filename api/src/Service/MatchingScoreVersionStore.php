<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JobOffer;
use App\Entity\JobOfferMatchingScoreState;
use Doctrine\ORM\EntityManagerInterface;

final class MatchingScoreVersionStore
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function versionFor(JobOffer $job): int
    {
        if ($job->getId() === null) {
            return 0;
        }

        $state = $this->em->getRepository(JobOfferMatchingScoreState::class)->findOneBy(['jobOffer' => $job]);

        return $state instanceof JobOfferMatchingScoreState ? $state->getVersion() : 0;
    }

    public function mark(JobOffer $job, int $version = MatchingScoreVersion::CURRENT): void
    {
        if ($job->getId() === null) {
            $this->em->persist(new JobOfferMatchingScoreState($job, $version));

            return;
        }

        $repository = $this->em->getRepository(JobOfferMatchingScoreState::class);
        $state = $repository->findOneBy(['jobOffer' => $job]);

        if (!$state instanceof JobOfferMatchingScoreState) {
            $state = new JobOfferMatchingScoreState($job, $version);
            $this->em->persist($state);

            return;
        }

        $state->markVersion($version);
    }
}
