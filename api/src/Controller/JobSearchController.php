<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JobCatalogResetService;
use App\Service\JobProfileCleanupService;
use App\Service\JobSearchSyncService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/job-search')]
final class JobSearchController
{
    public function __construct(
        private JobSearchSyncService $syncService,
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

        return new JsonResponse($this->syncService->sync(
            $force,
            null,
            $force ? 'manual' : 'page-load',
        ));
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

        $sync = $this->syncService->sync(true, null, 'catalog-reset');
        $syncBusy = (bool) ($sync['busy'] ?? false);

        return new JsonResponse([
            'message' => $syncBusy
                ? 'Catalogue supprimé. Une autre synchronisation a démarré et va recharger les offres.'
                : 'Catalogue supprimé puis resynchronisé depuis les sources actives.',
            'reset' => $reset,
            'sync' => $sync,
        ]);
    }
}
