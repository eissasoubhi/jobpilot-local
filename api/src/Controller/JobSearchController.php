<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JobSearchSyncService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/job-search')]
final class JobSearchController
{
    public function __construct(private JobSearchSyncService $syncService)
    {
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
}
