<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CustomScraperSource;
use App\Service\CustomScraperMultiSearchBudgetPlanner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CustomScraperSearchPlanController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CustomScraperMultiSearchBudgetPlanner $planner,
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
        $plan = $this->planner->plan($source);
        $searches = $plan['searches'];
        $configured = is_string($configuration['searchUrlTemplate'] ?? null)
            && trim((string) $configuration['searchUrlTemplate']) !== ''
            && is_array($configuration['searchKeywords'] ?? null)
            && $configuration['searchKeywords'] !== [];

        return new JsonResponse([
            'sourceId' => $configuration['id'],
            'sourceName' => $configuration['name'],
            'configured' => $configured,
            'searchCount' => count($searches),
            'maxPagesPerSearch' => $plan['maxPagesPerSearch'],
            'requestedMaxListingRequests' => $plan['requestedMaxListingRequests'],
            'estimatedMaxListingRequests' => $plan['globalPageBudget'],
            'globalPageBudget' => $plan['globalPageBudget'],
            'budgetLimited' => $plan['budgetLimited'],
            'searches' => $searches,
        ]);
    }
}
