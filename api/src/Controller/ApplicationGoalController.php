<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ApplicationGoalProgressService;
use App\Service\LocalDataService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/application-goals')]
final class ApplicationGoalController
{
    public function __construct(
        private ApplicationGoalProgressService $goals,
        private LocalDataService $data,
        private EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse($this->goals->snapshot());
    }

    #[Route('', methods: ['PUT'])]
    public function save(Request $request): JsonResponse
    {
        try {
            $this->data->settings()->fill([
                'applicationGoals' => $request->toArray(),
            ]);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }

        $this->em->flush();

        return new JsonResponse($this->goals->snapshot());
    }
}
