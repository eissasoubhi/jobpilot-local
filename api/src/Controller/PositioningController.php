<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CvDocument;
use App\Entity\JobOffer;
use App\Entity\Positioning;
use App\Service\DuplicatePositioningDetector;
use App\Service\LocalDataService;
use App\Service\TjmCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/positionings')]
final class PositioningController
{
    public function __construct(
        private EntityManagerInterface $em,
        private DuplicatePositioningDetector $duplicates,
        private TjmCalculator $tjm,
        private LocalDataService $data,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = $this->em->getRepository(Positioning::class)->findBy([], ['updatedAt' => 'DESC']);

        return new JsonResponse(array_map(static fn (Positioning $positioning): array => $positioning->toArray(), $items));
    }

    #[Route('/check-duplicate', methods: ['POST'])]
    public function check(Request $request): JsonResponse
    {
        return new JsonResponse($this->duplicates->check($request->toArray()));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $this->validateRequiredFields($payload);

        $duplicate = $this->duplicates->check($payload);
        if ($duplicate['duplicate'] && empty($payload['force'])) {
            return new JsonResponse([
                'error' => 'Risque de double positionnement. Validation manuelle obligatoire.',
                'duplicateCheck' => $duplicate,
            ], 409);
        }

        $job = $this->resolveJob($payload['jobOfferId'] ?? null);
        $cv = $this->resolveCv($payload['cvDocumentId'] ?? null);
        $settings = $this->data->settings();

        if ($this->nullableInt($payload['proposedTjm'] ?? null) === null) {
            $payload['proposedTjm'] = $this->tjm->calculate(
                $this->nullableInt($payload['advertisedTjmFixed'] ?? null),
                $this->nullableInt($payload['advertisedTjmMin'] ?? null),
                $this->nullableInt($payload['advertisedTjmMax'] ?? null),
                (string) ($payload['location'] ?? ''),
                (string) ($payload['remotePolicy'] ?? ''),
                $settings,
                true,
            );
        } else {
            $payload['proposedTjm'] = min((int) $payload['proposedTjm'], $settings->getMaximumTjm());
        }

        if ($payload['proposedTjm'] === null || (int) $payload['proposedTjm'] < $settings->getMinimumFreelanceTjm()) {
            throw new \InvalidArgumentException('Le TJM proposé est inférieur au minimum freelance configuré.');
        }

        if ($this->nullableInt($payload['acceptedTjm'] ?? null) !== null) {
            $accepted = (int) $payload['acceptedTjm'];
            if ($accepted < $settings->getMinimumFreelanceTjm()) {
                throw new \InvalidArgumentException('Le TJM accepté est inférieur au minimum freelance configuré.');
            }
            $payload['acceptedTjm'] = min($accepted, $settings->getMaximumTjm());
        }

        $positioning = (new Positioning())->fill($payload, $job, $cv);
        $this->em->persist($positioning);
        $this->em->flush();

        return new JsonResponse($positioning->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(Positioning $positioning, Request $request): JsonResponse
    {
        $positioning->fill($request->toArray());
        $this->em->flush();

        return new JsonResponse($positioning->toArray());
    }

    private function validateRequiredFields(array $payload): void
    {
        foreach ([
            'finalClient' => 'Le client final',
            'agency' => 'L’agence',
            'recruiterName' => 'Le commercial',
            'missionTitle' => 'L’intitulé de la mission',
        ] as $field => $label) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                throw new \InvalidArgumentException($label.' est obligatoire.');
            }
        }
    }

    private function resolveJob(mixed $id): ?JobOffer
    {
        if ($id === null || $id === '') {
            return null;
        }

        $job = $this->em->find(JobOffer::class, (int) $id);
        if (!$job instanceof JobOffer) {
            throw new \InvalidArgumentException('Offre liée introuvable.');
        }

        return $job;
    }

    private function resolveCv(mixed $id): ?CvDocument
    {
        if ($id === null || $id === '') {
            return null;
        }

        $cv = $this->em->find(CvDocument::class, (int) $id);
        if (!$cv instanceof CvDocument) {
            throw new \InvalidArgumentException('CV lié introuvable.');
        }

        return $cv;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
