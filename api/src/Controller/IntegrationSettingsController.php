<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Integration\ExternalIntegrationConfigurationStore;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings/integrations')]
final class IntegrationSettingsController
{
    public function __construct(private readonly ExternalIntegrationConfigurationStore $configuration)
    {
    }

    #[Route('', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse($this->configuration->publicConfigurations());
    }

    #[Route('/{integration}', methods: ['PUT'])]
    public function save(string $integration, Request $request): JsonResponse
    {
        try {
            return new JsonResponse($this->configuration->save($integration, $request->toArray()));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }
}
