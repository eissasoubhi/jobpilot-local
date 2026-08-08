<?php
namespace App\Controller;

use App\Entity\Application;
use App\Entity\UserSettings;
use App\Service\ApplicationCvRepairService;
use App\Service\ApplicationMessageUpgradeService;
use App\Service\JobProfileTechnologyComparisonService;
use App\Service\LocalDataService;
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
        private LocalDataService $data,
        private JobProfileTechnologyComparisonService $technologyComparison,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = $this->em->getRepository(Application::class)->findBy([], ['updatedAt' => 'DESC']);
        $this->cvRepair->repairAll($items);
        $this->messageUpgrade->upgradeLegacyMessages($items);
        $settings = $this->data->settings();

        return new JsonResponse(array_map(
            fn (Application $application): array => $this->serialize($application, $settings),
            $items,
        ));
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(Application $application, Request $request): JsonResponse
    {
        $application->fill($request->toArray());
        $this->em->flush();

        return new JsonResponse($this->serialize($application, $this->data->settings()));
    }

    /** @return array<string, mixed> */
    private function serialize(Application $application, UserSettings $settings): array
    {
        return [
            ...$application->toArray(),
            'profileComparison' => $this->technologyComparison->compare($application->getJobOffer(), $settings),
        ];
    }
}
