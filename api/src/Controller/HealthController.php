<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(private Connection $connection) {}

    #[Route('/api/health/live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'check' => 'liveness',
        ]);
    }

    #[Route('/api/health/ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();
        } catch (\Throwable) {
            return new JsonResponse([
                'status' => 'unavailable',
                'check' => 'readiness',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse([
            'status' => 'ok',
            'check' => 'readiness',
        ]);
    }
}
