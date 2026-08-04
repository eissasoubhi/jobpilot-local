<?php
namespace App\Controller;

use App\Entity\Application;
use App\Service\ApplicationCvRepairService;
use App\Service\ApplicationMessageUpgradeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/applications')]
final class ApplicationController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ApplicationCvRepairService $cvRepair,
        private ApplicationMessageUpgradeService $messageUpgrade,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = $this->em->getRepository(Application::class)->findBy([], ['updatedAt' => 'DESC']);
        $this->cvRepair->repairAll($items);
        $this->messageUpgrade->upgradeLegacyMessages($items);

        return new JsonResponse(array_map(static fn (Application $application) => $application->toArray(), $items));
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(Application $application, Request $request): JsonResponse
    {
        $application->fill($request->toArray());
        $this->em->flush();

        return new JsonResponse($application->toArray());
    }
}
