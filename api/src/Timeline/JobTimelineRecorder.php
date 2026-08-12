<?php

declare(strict_types=1);

namespace App\Timeline;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\JobTimelineEvent;
use Doctrine\ORM\EntityManagerInterface;

final class JobTimelineRecorder
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Records an event in the current Doctrine unit of work without flushing it.
     * The caller keeps transaction boundaries explicit.
     *
     * @param array<string, mixed> $payload
     */
    public function record(
        JobOffer $jobOffer,
        string $type,
        array $payload = [],
        ?Application $application = null,
        ?\DateTimeImmutable $occurredAt = null,
        string $source = 'jobpilot',
    ): JobTimelineEvent {
        $event = new JobTimelineEvent(
            $jobOffer,
            $type,
            $payload,
            $application,
            $occurredAt,
            $source,
        );

        $this->em->persist($event);

        return $event;
    }
}
