<?php
namespace App\Controller;

use App\Entity\Application;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/applications')]
final class ApplicationController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = $this->em->getRepository(Application::class)->findBy([], ['updatedAt'=>'DESC']);
        return new JsonResponse(array_map(static fn(Application $a) => $a->toArray(), $items));
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(Application $application, Request $request): JsonResponse
    {
        $application->fill($request->toArray()); $this->em->flush();
        return new JsonResponse($application->toArray());
    }
}
