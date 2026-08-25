<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\JobOffer;
use App\Entity\JobTimelineEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/jobs')]
final class JobTimelineController
{
    private const MAX_EVENTS = 200;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/{id}/timeline', methods: ['GET'])]
    public function list(JobOffer $job): JsonResponse
    {
        $events = $this->em->getRepository(JobTimelineEvent::class)->findBy(
            ['jobOffer' => $job],
            ['occurredAt' => 'DESC', 'id' => 'DESC'],
            self::MAX_EVENTS,
        );

        return new JsonResponse(array_map(
            static fn (JobTimelineEvent $event): array => $event->toArray(),
            $events,
        ));
    }
}
