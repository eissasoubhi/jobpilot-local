<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JobSearchSyncService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/connectors')]
final class ConnectorController
{
    public function __construct(private JobSearchSyncService $syncService)
    {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->syncService->connectors());
    }

    #[Route('/history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query->get('limit', 30)));

        return new JsonResponse($this->syncService->history($limit));
    }

    #[Route('/{code}', methods: ['PATCH'])]
    public function update(string $code, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'La configuration du connecteur est invalide.'], 400);
        }

        if (!array_key_exists('enabled', $payload) || !is_bool($payload['enabled'])) {
            return new JsonResponse(['error' => 'Le champ booléen enabled est obligatoire.'], 400);
        }

        return new JsonResponse($this->syncService->setEnabled($code, $payload['enabled']));
    }

    #[Route('/{code}/sync', methods: ['POST'])]
    public function sync(string $code): JsonResponse
    {
        return new JsonResponse($this->syncService->sync(true, $code, 'manual'));
    }
}
