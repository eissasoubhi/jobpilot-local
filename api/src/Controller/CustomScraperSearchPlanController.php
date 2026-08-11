<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CustomScraperSource;
use App\Service\CustomScraperSearchPlanner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CustomScraperSearchPlanController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CustomScraperSearchPlanner $planner,
    ) {
    }

    #[Route('/api/custom-scrapers/{id}/search-plan', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function __invoke(int $id): JsonResponse
    {
        $source = $this->em->find(CustomScraperSource::class, $id);
        if (!$source instanceof CustomScraperSource) {
            return new JsonResponse(['error' => 'Source de scraping introuvable.'], 404);
        }

        $configuration = $source->toArray();
        $searches = $this->planner->plan($source);
        $configured = is_string($configuration['searchUrlTemplate'] ?? null)
            && trim((string) $configuration['searchUrlTemplate']) !== ''
            && is_array($configuration['searchKeywords'] ?? null)
            && $configuration['searchKeywords'] !== [];

        return new JsonResponse([
            'sourceId' => $source->getId(),
            'sourceName' => $configuration['name'],
            'configured' => $configured,
            'searchCount' => count($searches),
            'maxPagesPerSearch' => (int) $configuration['maxPages'],
            'estimatedMaxListingRequests' => count($searches) * (int) $configuration['maxPages'],
            'searches' => $searches,
        ]);
    }
}
