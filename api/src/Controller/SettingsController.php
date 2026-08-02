<?php
namespace App\Controller;

use App\Service\LocalDataService;
use App\Service\SourceRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings')]
final class SettingsController
{
    public function __construct(private LocalDataService $data, private EntityManagerInterface $em, private SourceRegistry $sources) {}

    #[Route('', methods: ['GET'])]
    public function get(): JsonResponse { return new JsonResponse($this->data->settings()->toArray()); }

    #[Route('', methods: ['PUT'])]
    public function save(Request $request): JsonResponse
    {
        $settings = $this->data->settings()->fill($request->toArray());
        $this->em->flush();
        return new JsonResponse($settings->toArray());
    }

    #[Route('/sources', methods: ['GET'])]
    public function sources(): JsonResponse { return new JsonResponse($this->sources->all()); }
}
