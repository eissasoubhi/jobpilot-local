<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route('/api/health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->readinessResponse(includeCheck: false);
    }

    #[Route('/api/health/live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'app' => 'JobPilot Local',
            'check' => 'liveness',
        ]);
    }

    #[Route('/api/health/ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        return $this->readinessResponse(includeCheck: true);
    }

    private function readinessResponse(bool $includeCheck): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $payload = ['status' => 'unavailable', 'app' => 'JobPilot Local'];
            if ($includeCheck) {
                $payload['check'] = 'readiness';
            }

            return new JsonResponse($payload, Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $payload = ['status' => 'ok', 'app' => 'JobPilot Local'];
        if ($includeCheck) {
            $payload['check'] = 'readiness';
        }

        return new JsonResponse($payload);
    }
}
