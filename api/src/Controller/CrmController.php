<?php

declare(strict_types=1);

namespace App\Controller;

use App\Crm\Application\OrganizationCrmDirectoryBuilder;
use App\Entity\Application;
use App\Entity\InboxMessage;
use App\Entity\Positioning;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/crm')]
final class CrmController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrganizationCrmDirectoryBuilder $directoryBuilder,
    ) {
    }

    #[Route('/organizations', methods: ['GET'])]
    public function organizations(): JsonResponse
    {
        return new JsonResponse($this->directoryBuilder->build(
            $this->entityManager->getRepository(Application::class)->findBy([], ['updatedAt' => 'DESC']),
            $this->entityManager->getRepository(Positioning::class)->findBy([], ['updatedAt' => 'DESC']),
            $this->entityManager->getRepository(InboxMessage::class)->findBy([], ['receivedAt' => 'DESC']),
        ));
    }
}
