<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Application;
use App\Service\JobProfileTechnologyComparisonService;
use App\Service\LocalDataService;
use App\Service\ReviewDecisionUndoService;
use App\Timeline\JobTimelineEventType;
use App\Timeline\JobTimelineRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/applications')]
final class ReviewDecisionController
{
    public function __construct(
        private EntityManagerInterface $em,
        private LocalDataService $data,
        private JobProfileTechnologyComparisonService $technologyComparison,
        private ReviewDecisionUndoService $undoService,
        private JobTimelineRecorder $timeline,
    ) {}

    #[Route('/{id}/review-decision/undo', methods: ['POST'])]
    public function undo(Application $application): JsonResponse
    {
        try {
            $previousStatus = $this->undoService->undo($application);
        } catch (\LogicException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        }

        $job = $application->getJobOffer();
        $this->timeline->record(
            $job,
            JobTimelineEventType::PREPARATION_UPDATED,
            ['reviewDecisionUndone' => $previousStatus],
            $application,
            null,
            'review-undo',
        );
        $this->em->flush();

        return new JsonResponse([
            ...$application->toArray(),
            'profileComparison' => $this->technologyComparison->compare($job, $this->data->settings()),
        ]);
    }
}
