<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Ai\AiMatchingConfigurationStore;
use App\Service\Ai\AiQuotaManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings/ai')]
final class AiSettingsController
{
    public function __construct(
        private readonly AiMatchingConfigurationStore $configuration,
        private readonly AiQuotaManager $quotaManager,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse($this->payload());
    }

    #[Route('', methods: ['PUT'])]
    public function save(Request $request): JsonResponse
    {
        try {
            $this->configuration->save($request->toArray());

            return new JsonResponse($this->payload());
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $public = $this->configuration->publicConfiguration();
        $effective = $this->configuration->effective();
        $public['quotaUsage'] = $this->quotaManager->status(
            'gemini',
            $effective['model'],
            $effective['quota'],
        );

        return $public;
    }
}
