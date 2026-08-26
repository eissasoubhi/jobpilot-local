<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InboxMessage;
use App\Entity\InboxSenderClassificationRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/integrations/gmail/messages')]
final class InboxClassificationController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/{id}/sender-classification', methods: ['PUT'])]
    public function save(InboxMessage $message, Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Correction Inbox invalide.'], 400);
        }

        $category = strtoupper(trim((string) ($data['category'] ?? 'JOB_ALERT')));
        if (!in_array($category, ['JOB_ALERT', 'MARKETING'], true)) {
            return new JsonResponse(['error' => 'Seules les catégories alerte emploi et marketing sont autorisées.'], 422);
        }

        $senderKey = InboxSenderClassificationRule::senderKey($message->getSender());
        if ($senderKey === '') {
            return new JsonResponse(['error' => 'Impossible d’identifier une adresse e-mail fiable pour cet expéditeur.'], 422);
        }

        $repository = $this->em->getRepository(InboxSenderClassificationRule::class);
        $rule = $repository->findOneBy(['senderKey' => $senderKey]);
        if (!$rule instanceof InboxSenderClassificationRule) {
            $rule = new InboxSenderClassificationRule($senderKey, $category);
            $this->em->persist($rule);
        } else {
            $rule->setCategory($category);
        }

        $reason = $category === 'JOB_ALERT'
            ? 'Correction utilisateur persistante : cet expéditeur est toujours classé comme alerte emploi.'
            : 'Correction utilisateur persistante : cet expéditeur est toujours classé comme newsletter ou promotion.';
        $message->overrideClassification($category, $reason, false);
        $this->em->flush();

        return new JsonResponse([
            'senderKey' => $rule->getSenderKey(),
            'category' => $rule->getCategory(),
            'message' => $message->toArray(),
        ]);
    }
}
