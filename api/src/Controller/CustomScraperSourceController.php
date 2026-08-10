<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CustomScraperSource;
use App\Service\CustomScraperDiagnosticService;
use App\Service\CustomScraperExtractionService;
use App\Service\CustomScraperPresetCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/custom-scrapers')]
final class CustomScraperSourceController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CustomScraperDiagnosticService $diagnostics,
        private CustomScraperExtractionService $extraction,
        private CustomScraperPresetCatalog $presets,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $sources = $this->em->getRepository(CustomScraperSource::class)->findBy([], ['name' => 'ASC']);

        return new JsonResponse(array_map(
            static fn (CustomScraperSource $source): array => $source->toArray(),
            $sources,
        ));
    }

    #[Route('/presets', methods: ['GET'])]
    public function presets(): JsonResponse
    {
        return new JsonResponse($this->presets->all());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->payload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if (($payload['authorizationConfirmed'] ?? false) !== true) {
            return new JsonResponse([
                'error' => 'Tu dois confirmer avoir vérifié que cette source autorise la collecte avant de l’ajouter.',
            ], 400);
        }

        try {
            $source = new CustomScraperSource(
                (string) ($payload['name'] ?? ''),
                (string) ($payload['listingUrl'] ?? ''),
            );
            $source->fill($payload);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }

        $duplicate = $this->em->getRepository(CustomScraperSource::class)->findOneBy([
            'listingUrl' => $source->toArray()['listingUrl'],
        ]);
        if ($duplicate !== null) {
            return new JsonResponse(['error' => 'Cette URL de liste est déjà enregistrée.'], 409);
        }

        $this->em->persist($source);
        $this->em->flush();

        return new JsonResponse($source->toArray(), 201);
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $source = $this->em->find(CustomScraperSource::class, $id);
        if (!$source instanceof CustomScraperSource) {
            return new JsonResponse(['error' => 'Source de scraping introuvable.'], 404);
        }

        $payload = $this->payload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        try {
            $source->fill($payload);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }

        $duplicate = $this->em->getRepository(CustomScraperSource::class)->findOneBy([
            'listingUrl' => $source->toArray()['listingUrl'],
        ]);
        if ($duplicate !== null && $duplicate !== $source) {
            return new JsonResponse(['error' => 'Cette URL de liste est déjà enregistrée.'], 409);
        }

        $this->em->flush();

        return new JsonResponse($source->toArray());
    }

    #[Route('/{id}/diagnose', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function diagnose(int $id): JsonResponse
    {
        $source = $this->em->find(CustomScraperSource::class, $id);
        if (!$source instanceof CustomScraperSource) {
            return new JsonResponse(['error' => 'Source de scraping introuvable.'], 404);
        }

        try {
            return new JsonResponse($this->diagnostics->diagnose($source));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\RuntimeException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/{id}/preview', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function preview(int $id): JsonResponse
    {
        $source = $this->em->find(CustomScraperSource::class, $id);
        if (!$source instanceof CustomScraperSource) {
            return new JsonResponse(['error' => 'Source de scraping introuvable.'], 404);
        }

        try {
            return new JsonResponse($this->extraction->preview($source));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\RuntimeException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $source = $this->em->find(CustomScraperSource::class, $id);
        if (!$source instanceof CustomScraperSource) {
            return new JsonResponse(['error' => 'Source de scraping introuvable.'], 404);
        }

        $this->em->remove($source);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    /** @return array<string, mixed>|JsonResponse */
    private function payload(Request $request): array|JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Le JSON envoyé est invalide.'], 400);
        }

        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Le JSON envoyé est invalide.'], 400);
        }

        return $payload;
    }
}
