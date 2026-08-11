<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LocalDataService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profile')]
final class ProfileController
{
    public function __construct(private LocalDataService $data, private EntityManagerInterface $em) {}

    #[Route('', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse($this->data->profile()->toArray());
    }

    #[Route('/autofill', methods: ['GET'])]
    public function autofill(): JsonResponse
    {
        return new JsonResponse($this->data->profile()->toAutofillArray());
    }

    #[Route('', methods: ['PUT'])]
    public function save(Request $request): JsonResponse
    {
        $profile = $this->data->profile()->fill($request->toArray());
        $this->em->flush();

        return new JsonResponse($profile->toArray());
    }
}
