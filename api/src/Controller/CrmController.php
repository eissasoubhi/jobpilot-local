<?php

declare(strict_types=1);

namespace App\Controller;

use App\Crm\Application\OrganizationCrmAnnotationApplier;
use App\Crm\Application\OrganizationCrmDirectoryBuilder;
use App\Entity\Application;
use App\Entity\CrmOrganizationAnnotation;
use App\Entity\InboxMessage;
use App\Entity\Positioning;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/crm')]
final class CrmController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrganizationCrmDirectoryBuilder $directoryBuilder,
        private OrganizationCrmAnnotationApplier $annotationApplier,
    ) {
    }

    #[Route('/organizations', methods: ['GET'])]
    public function organizations(): JsonResponse
    {
        return new JsonResponse($this->annotationApplier->apply(
            $this->buildBaseDirectory(),
            $this->entityManager->getRepository(CrmOrganizationAnnotation::class)->findAll(),
        ));
    }

    #[Route('/organizations/{organizationKey}/annotation', methods: ['PUT'])]
    public function updateAnnotation(string $organizationKey, Request $request): JsonResponse
    {
        $organizationKey = trim($organizationKey);
        if (!$this->organizationExists($organizationKey)) {
            return new JsonResponse(
                ['error' => 'CRM organization was not found.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        $repository = $this->entityManager->getRepository(CrmOrganizationAnnotation::class);
        $annotation = $repository->findOneBy(['organizationKey' => $organizationKey]);
        if (!$annotation instanceof CrmOrganizationAnnotation) {
            $annotation = new CrmOrganizationAnnotation($organizationKey);
        }

        try {
            $payload = $request->toArray();
            $annotation->update(
                $payload['displayName'] ?? null,
                $payload['note'] ?? null,
            );
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(
                ['error' => $exception->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($annotation->isEmpty()) {
            if ($annotation->getId() !== null) {
                $this->entityManager->remove($annotation);
                $this->entityManager->flush();
            }

            return new JsonResponse([
                'organizationKey' => $organizationKey,
                'annotation' => null,
            ]);
        }

        $this->entityManager->persist($annotation);
        $this->entityManager->flush();

        return new JsonResponse([
            'organizationKey' => $organizationKey,
            'annotation' => $annotation->toArray(),
        ]);
    }

    /** @return array<string, mixed> */
    private function buildBaseDirectory(): array
    {
        return $this->directoryBuilder->build(
            $this->entityManager->getRepository(Application::class)->findBy([], ['updatedAt' => 'DESC']),
            $this->entityManager->getRepository(Positioning::class)->findBy([], ['updatedAt' => 'DESC']),
            $this->entityManager->getRepository(InboxMessage::class)->findBy([], ['receivedAt' => 'DESC']),
        );
    }

    private function organizationExists(string $organizationKey): bool
    {
        if ($organizationKey === '') {
            return false;
        }

        foreach ($this->buildBaseDirectory()['organizations'] as $organization) {
            if (($organization['key'] ?? null) === $organizationKey) {
                return true;
            }
        }

        return false;
    }
}
