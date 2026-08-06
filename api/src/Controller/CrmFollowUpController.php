<?php

declare(strict_types=1);

namespace App\Controller;

use App\Crm\Application\OrganizationCrmDirectoryBuilder;
use App\Entity\Application;
use App\Entity\CrmFollowUpTask;
use App\Entity\InboxMessage;
use App\Entity\Positioning;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/crm')]
final class CrmFollowUpController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrganizationCrmDirectoryBuilder $directoryBuilder,
    ) {
    }

    #[Route('/follow-ups', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = strtolower(trim((string) $request->query->get('status', 'open')));
        if (!in_array($status, ['open', 'completed', 'all'], true)) {
            return new JsonResponse(['error' => 'Follow-up status must be open, completed or all.'], Response::HTTP_BAD_REQUEST);
        }

        $criteria = match ($status) {
            'open' => ['completedAt' => null],
            'completed' => ['completedAt' => ['not' => null]],
            default => [],
        };

        $repository = $this->entityManager->getRepository(CrmFollowUpTask::class);
        $tasks = $status === 'completed'
            ? array_values(array_filter(
                $repository->findBy([], ['dueAt' => 'ASC', 'id' => 'ASC']),
                static fn (CrmFollowUpTask $task): bool => $task->isCompleted(),
            ))
            : $repository->findBy($criteria, ['dueAt' => 'ASC', 'id' => 'ASC']);

        return new JsonResponse(array_map(
            static fn (CrmFollowUpTask $task): array => $task->toArray(),
            $tasks,
        ));
    }

    #[Route('/organizations/{organizationKey}/follow-ups', methods: ['POST'])]
    public function create(string $organizationKey, Request $request): JsonResponse
    {
        $organizationKey = trim($organizationKey);

        try {
            $payload = $request->toArray();
            $contactKey = $this->optionalKey($payload['contactKey'] ?? null);
            if (!$this->targetExists($organizationKey, $contactKey)) {
                return new JsonResponse(
                    ['error' => $contactKey === null
                        ? 'CRM organization was not found.'
                        : 'CRM contact was not found in this organization.'],
                    Response::HTTP_NOT_FOUND,
                );
            }

            $task = new CrmFollowUpTask(
                $organizationKey,
                $contactKey,
                $payload['title'] ?? null,
                $payload['note'] ?? null,
                $this->dueDate($payload['dueAt'] ?? null),
            );
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Follow-up payload must be valid JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return new JsonResponse($task->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/follow-ups/{id}', methods: ['PATCH'])]
    public function updateCompletion(int $id, Request $request): JsonResponse
    {
        $task = $this->entityManager->getRepository(CrmFollowUpTask::class)->find($id);
        if (!$task instanceof CrmFollowUpTask) {
            return new JsonResponse(['error' => 'CRM follow-up task was not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Follow-up payload must be valid JSON.'], Response::HTTP_BAD_REQUEST);
        }

        if (!array_key_exists('completed', $payload) || !is_bool($payload['completed'])) {
            return new JsonResponse(['error' => 'The completed boolean field is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $task->setCompleted($payload['completed']);
        $this->entityManager->flush();

        return new JsonResponse($task->toArray());
    }

    /** @return array<string, mixed> */
    private function baseDirectory(): array
    {
        return $this->directoryBuilder->build(
            $this->entityManager->getRepository(Application::class)->findBy([], ['updatedAt' => 'DESC']),
            $this->entityManager->getRepository(Positioning::class)->findBy([], ['updatedAt' => 'DESC']),
            $this->entityManager->getRepository(InboxMessage::class)->findBy([], ['receivedAt' => 'DESC']),
        );
    }

    private function targetExists(string $organizationKey, ?string $contactKey): bool
    {
        if ($organizationKey === '') {
            return false;
        }

        foreach ($this->baseDirectory()['organizations'] ?? [] as $organization) {
            if (($organization['key'] ?? null) !== $organizationKey) {
                continue;
            }
            if ($contactKey === null) {
                return true;
            }

            foreach ($organization['contacts'] ?? [] as $contact) {
                if (($contact['key'] ?? null) === $contactKey) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    private function optionalKey(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function dueDate(mixed $value): \DateTimeImmutable
    {
        $value = trim((string) $value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Follow-up dueAt must use the YYYY-MM-DD format.');
        }

        return $date;
    }
}
