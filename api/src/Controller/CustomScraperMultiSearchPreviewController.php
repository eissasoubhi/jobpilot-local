<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CustomScraperSource;
use App\Service\CustomScraperMultiSearchListingCollector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CustomScraperMultiSearchPreviewController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CustomScraperMultiSearchListingCollector $collector,
    ) {
    }

    #[Route('/api/custom-scrapers/{id}/search-preview', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $source = $this->em->find(CustomScraperSource::class, $id);
        if (!$source instanceof CustomScraperSource) {
            return new JsonResponse(['error' => 'Source de scraping introuvable.'], 404);
        }

        try {
            return new JsonResponse($this->collector->collect($source));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\RuntimeException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        }
    }
}
