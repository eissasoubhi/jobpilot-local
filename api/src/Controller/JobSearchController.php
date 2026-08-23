<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JobCatalogResetService;
use App\Service\JobProfileCleanupService;
use App\Service\JobSearchSyncQueue;
use App\Service\JobSearchSyncService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/job-search')]
final class JobSearchController
{
    public function __construct(
        private JobSearchSyncService $syncService,
        private JobSearchSyncQueue $syncQueue,
        private JobCatalogResetService $catalogReset,
        private JobProfileCleanupService $profileCleanup,
    ) {
    }

    #[Route('/status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return new JsonResponse($this->syncService->status());
    }

    #[Route('/sync', methods: ['POST'])]
    public function sync(Request $request): JsonResponse
    {
        $force = filter_var($request->query->get('force', '0'), FILTER_VALIDATE_BOOL);
        $queued = $this->syncQueue->enqueue(
            $force,
            null,
            $force ? 'manual' : 'page-load',
        );
        $id = (string) ($queued['id'] ?? '');

        return new JsonResponse(
            $id !== '' ? $this->syncSnapshot($this->syncQueue->get($id)) : ['job' => null, 'connectors' => []],
            JsonResponse::HTTP_ACCEPTED,
        );
    }

    #[Route('/sync/current', methods: ['GET'], priority: 10)]
    public function currentSyncRun(): JsonResponse
    {
        return new JsonResponse($this->syncSnapshot($this->syncQueue->current()));
    }

    #[Route('/sync/{runId}', methods: ['GET'])]
    public function syncRun(string $runId): JsonResponse
    {
        $job = $this->syncQueue->get($runId);
        if ($job === null) {
            return new JsonResponse([
                'error' => 'sync_not_found',
                'message' => 'Cette recherche d’offres est introuvable ou a été remplacée.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->syncSnapshot($job));
    }

    #[Route('/cleanup-profile-mismatches', methods: ['POST'])]
    public function cleanupProfileMismatches(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $confirmation = (string) ($payload['confirmation'] ?? '');

        try {
            $cleanup = $this->profileCleanup->cleanup($confirmation);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 400);
        }

        if ($cleanup['busy']) {
            return new JsonResponse([
                'message' => 'Une synchronisation est déjà en cours. Réessaie le nettoyage une fois terminée.',
                'cleanup' => $cleanup,
            ], 409);
        }

        return new JsonResponse([
            'message' => sprintf(
                '%d offre(s) hors profil supprimée(s). %d offre(s) conservée(s).',
                $cleanup['deletedOffers'],
                $cleanup['kept'],
            ),
            'cleanup' => $cleanup,
        ]);
    }

    #[Route('/reset', methods: ['POST'])]
    public function reset(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $confirmation = (string) ($payload['confirmation'] ?? '');

        try {
            $reset = $this->catalogReset->reset($confirmation);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 400);
        }

        if ($reset['busy']) {
            return new JsonResponse([
                'message' => 'Une synchronisation est déjà en cours. Réessaie la réinitialisation une fois terminée.',
                'reset' => $reset,
            ], 409);
        }

        $queued = $this->syncQueue->enqueue(true, null, 'catalog-reset');
        $id = (string) ($queued['id'] ?? '');

        return new JsonResponse([
            'message' => 'Catalogue supprimé. La resynchronisation a été placée en arrière-plan.',
            'reset' => $reset,
            'sync' => $id !== '' ? $this->syncQueue->get($id) : null,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    /**
     * @param array<string, mixed>|null $job
     * @return array{job: array<string, mixed>|null, connectors: list<array<string, mixed>>}
     */
    private function syncSnapshot(?array $job): array
    {
        return [
            'job' => $job,
            'connectors' => $job === null ? [] : $this->syncService->connectors(),
        ];
    }
}
