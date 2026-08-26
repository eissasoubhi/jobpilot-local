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
        $connectorCode = null;
        $selectedConnectorCodes = null;
        $trigger = $force ? 'manual' : 'page-load';

        if ($request->getContent() !== '') {
            try {
                $payload = $request->toArray();
            } catch (\Throwable) {
                return new JsonResponse([
                    'error' => 'invalid_sync_selection',
                    'message' => 'La sélection de connecteurs est invalide.',
                ], JsonResponse::HTTP_BAD_REQUEST);
            }

            if (array_key_exists('connectorCodes', $payload)) {
                try {
                    $selectedConnectorCodes = $this->validateConnectorSelection($payload['connectorCodes']);
                } catch (\InvalidArgumentException $exception) {
                    return new JsonResponse([
                        'error' => 'invalid_sync_selection',
                        'message' => $exception->getMessage(),
                    ], JsonResponse::HTTP_BAD_REQUEST);
                }

                $force = true;
                $connectorCode = implode(',', $selectedConnectorCodes);
                $trigger = 'manual-selection';
            }
        }

        $queued = $this->syncQueue->enqueue(
            $force,
            $connectorCode,
            $trigger,
            $selectedConnectorCodes,
        );
        $id = (string) ($queued['id'] ?? '');

        return new JsonResponse(
            $this->syncSnapshot($id !== '' ? $this->syncQueue->get($id) : null),
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

    /** @return list<string> */
    private function validateConnectorSelection(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('connectorCodes doit être une liste de connecteurs.');
        }

        $selected = [];
        foreach ($value as $code) {
            if (!is_string($code) || trim($code) === '') {
                throw new \InvalidArgumentException('Chaque code de connecteur sélectionné doit être une chaîne non vide.');
            }
            $selected[] = strtolower(trim($code));
        }
        $selected = array_values(array_unique($selected));
        if ($selected === []) {
            throw new \InvalidArgumentException('Sélectionne au moins un connecteur à synchroniser.');
        }

        $available = [];
        foreach ($this->syncService->connectors() as $connector) {
            $code = strtolower(trim((string) ($connector['code'] ?? '')));
            if ($code !== '') {
                $available[$code] = $connector;
            }
        }

        foreach ($selected as $code) {
            if (!isset($available[$code])) {
                throw new \InvalidArgumentException(sprintf('Connecteur inconnu : %s.', $code));
            }
            $connector = $available[$code];
            if (($connector['enabled'] ?? true) === false) {
                throw new \InvalidArgumentException(sprintf('Le connecteur %s est désactivé.', $code));
            }
            if (($connector['configured'] ?? true) === false) {
                throw new \InvalidArgumentException(sprintf('Le connecteur %s n’est pas configuré.', $code));
            }
            if (($connector['collectionAllowed'] ?? true) === false) {
                throw new \InvalidArgumentException(sprintf('La politique de collecte bloque le connecteur %s.', $code));
            }
        }

        return $selected;
    }

    /**
     * @param array<string, mixed>|null $job
     * @return array{
     *   job: array<string, mixed>|null,
     *   connectors: list<array<string, mixed>>,
     *   worker: array{status: 'active'|'stale'|'missing', updatedAt: string|null}
     * }
     */
    private function syncSnapshot(?array $job): array
    {
        $connectors = $job === null ? [] : $this->syncService->connectors();
        $targets = is_array($job['targetConnectorCodes'] ?? null)
            ? array_values(array_filter($job['targetConnectorCodes'], 'is_string'))
            : null;

        if ($targets !== null) {
            $connectors = array_values(array_filter(
                $connectors,
                static fn (array $connector): bool => in_array(
                    strtolower((string) ($connector['code'] ?? '')),
                    $targets,
                    true,
                ),
            ));
        }

        $connectors = array_map(static function (array $connector): array {
            $profileFiltered = max(0, (int) ($connector['profileFiltered'] ?? 0));
            $lastResult = is_array($connector['lastResult'] ?? null) ? $connector['lastResult'] : [];
            $lastResult['profileFiltered'] = $profileFiltered;
            $connector['lastResult'] = $lastResult;

            return $connector;
        }, $connectors);

        return [
            'job' => $job,
            'connectors' => $connectors,
            'worker' => $this->syncQueue->workerStatus(),
        ];
    }
}
