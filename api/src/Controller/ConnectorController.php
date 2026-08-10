<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ConnectorDeadLetter;
use App\JobDiscovery\Application\ConnectorDeadLetterService;
use App\Service\JobSearchSyncService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/connectors')]
final class ConnectorController
{
    public function __construct(
        private JobSearchSyncService $syncService,
        private ConnectorDeadLetterService $deadLetters,
    ) {
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

    #[Route('/dead-letters', methods: ['GET'])]
    public function deadLetters(Request $request): JsonResponse
    {
        $state = strtoupper(trim((string) $request->query->get('state', ConnectorDeadLetter::STATE_OPEN)));
        $limit = max(1, min(200, (int) $request->query->get('limit', 50)));

        try {
            return new JsonResponse($this->deadLetters->list($state, $limit));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }
    }

    #[Route('/dead-letters/{id}/resolve', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function resolveDeadLetter(int $id): JsonResponse
    {
        try {
            return new JsonResponse($this->deadLetters->resolveById($id));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        }
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
