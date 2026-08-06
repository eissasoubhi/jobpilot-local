<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ConnectorSearchCriteriaService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/connectors/{code}/criteria')]
final class ConnectorSearchCriteriaController
{
    public function __construct(private ConnectorSearchCriteriaService $criteria)
    {
    }

    #[Route('', methods: ['GET'])]
    public function get(string $code): JsonResponse
    {
        try {
            return new JsonResponse($this->criteria->get($code));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(
                ['error' => $exception->getMessage()],
                Response::HTTP_NOT_FOUND,
            );
        }
    }

    #[Route('', methods: ['PUT'])]
    public function update(string $code, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(
                ['error' => 'Les critères de recherche doivent être envoyés en JSON.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            return new JsonResponse($this->criteria->update(
                $code,
                $payload['targetJobs'] ?? null,
                $payload['skills'] ?? null,
            ));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(
                ['error' => $exception->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }
}
