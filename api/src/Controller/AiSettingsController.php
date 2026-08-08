<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Ai\AiMatchingConfigurationStore;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings/ai')]
final class AiSettingsController
{
    public function __construct(private readonly AiMatchingConfigurationStore $configuration)
    {
    }

    #[Route('', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse($this->configuration->publicConfiguration());
    }

    #[Route('', methods: ['PUT'])]
    public function save(Request $request): JsonResponse
    {
        try {
            return new JsonResponse($this->configuration->save($request->toArray()));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }
}
