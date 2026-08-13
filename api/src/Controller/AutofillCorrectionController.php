<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AutofillCorrection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/autofill/corrections')]
final class AutofillCorrectionController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $host = strtolower(trim((string) $request->query->get('host', '')));
        if (!$this->validHost($host)) {
            return new JsonResponse(['error' => 'Le domaine est obligatoire et invalide.'], 400);
        }

        $corrections = $this->em->getRepository(AutofillCorrection::class)->findBy(
            ['host' => $host, 'enabled' => true],
            ['updatedAt' => 'DESC'],
        );

        return new JsonResponse(array_map(
            static fn (AutofillCorrection $correction): array => $correction->toArray(),
            $corrections,
        ));
    }

    #[Route('', methods: ['POST'])]
    public function upsert(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Le JSON envoyé est invalide.'], 400);
        }

        $host = strtolower(trim((string) ($payload['host'] ?? '')));
        $fingerprint = trim((string) ($payload['fieldFingerprint'] ?? ''));
        $canonicalKey = trim((string) ($payload['canonicalKey'] ?? ''));
        $controlKind = strtolower(trim((string) ($payload['controlKind'] ?? '')));
        $correctedValue = trim((string) ($payload['correctedValue'] ?? ''));
        $originalValue = array_key_exists('originalValue', $payload)
            ? trim((string) ($payload['originalValue'] ?? ''))
            : null;

        $repository = $this->em->getRepository(AutofillCorrection::class);
        $existing = $repository->findOneBy([
            'host' => $host,
            'fieldFingerprint' => $fingerprint,
            'canonicalKey' => $canonicalKey,
        ]);

        try {
            if ($existing instanceof AutofillCorrection) {
                $correction = $existing->update($correctedValue, $originalValue);
                $status = 200;
            } else {
                $correction = new AutofillCorrection(
                    $host,
                    $fingerprint,
                    $canonicalKey,
                    $controlKind,
                    $correctedValue,
                );
                if ($originalValue !== null && $originalValue !== '') {
                    $correction->update($correctedValue, $originalValue);
                }
                $this->em->persist($correction);
                $status = 201;
            }
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }

        $this->em->flush();

        return new JsonResponse($correction->toArray(), $status);
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $correction = $this->em->find(AutofillCorrection::class, $id);
        if (!$correction instanceof AutofillCorrection) {
            return new JsonResponse(['error' => 'Correction Autofill introuvable.'], 404);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Le JSON envoyé est invalide.'], 400);
        }

        if (array_key_exists('enabled', $payload)) {
            $correction->setEnabled(filter_var($payload['enabled'], FILTER_VALIDATE_BOOL));
        }
        $this->em->flush();

        return new JsonResponse($correction->toArray());
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $correction = $this->em->find(AutofillCorrection::class, $id);
        if (!$correction instanceof AutofillCorrection) {
            return new JsonResponse(['error' => 'Correction Autofill introuvable.'], 404);
        }

        $this->em->remove($correction);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    private function validHost(string $host): bool
    {
        return $host !== '' && mb_strlen($host) <= 255 && preg_match('/^[a-z0-9.-]+$/', $host) === 1;
    }
}
