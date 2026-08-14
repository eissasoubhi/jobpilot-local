<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Application;
use App\Service\JobProfileTechnologyComparisonService;
use App\Service\LocalDataService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/applications')]
final class OfferAvailabilityController
{
    public function __construct(
        private EntityManagerInterface $em,
        private LocalDataService $data,
        private JobProfileTechnologyComparisonService $technologyComparison,
    ) {}

    #[Route('/{id}/offer-unavailable', methods: ['POST'])]
    public function markUnavailable(Application $application): JsonResponse
    {
        if ($application->getStatus() === 'SUBMITTED') {
            return new JsonResponse([
                'error' => 'Une candidature déjà envoyée ne peut pas être marquée comme offre indisponible depuis la Review Queue.',
            ], 409);
        }

        $job = $application->getJobOffer();
        $job->fill(['status' => 'UNAVAILABLE']);
        $application->fill(['status' => 'OFFER_UNAVAILABLE']);
        $this->em->flush();

        return new JsonResponse([
            ...$application->toArray(),
            'profileComparison' => $this->technologyComparison->compare($job, $this->data->settings()),
        ]);
    }
}
