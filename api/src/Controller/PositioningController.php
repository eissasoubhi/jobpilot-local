<?php
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
        $items = $this->em->getRepository(Positioning::class)->findBy([], ['updatedAt'=>'DESC']);
        return new JsonResponse(array_map(static fn(Positioning $p) => $p->toArray(), $items));
    }

    #[Route('/check-duplicate', methods: ['POST'])]
    public function check(Request $request): JsonResponse { return new JsonResponse($this->duplicates->check($request->toArray())); }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $duplicate = $this->duplicates->check($data);
        if ($duplicate['duplicate'] && empty($data['force'])) return new JsonResponse(['error'=>'Risque de double positionnement. Validation manuelle obligatoire.', 'duplicateCheck'=>$duplicate], 409);
        $job = !empty($data['jobOfferId']) ? $this->em->find(JobOffer::class, (int) $data['jobOfferId']) : null;
        $cv = !empty($data['cvDocumentId']) ? $this->em->find(CvDocument::class, (int) $data['cvDocumentId']) : null;
        if (empty($data['proposedTjm'])) {
            $data['proposedTjm'] = $this->tjm->calculate(
                $this->nullableInt($data['advertisedTjmFixed'] ?? null),
                $this->nullableInt($data['advertisedTjmMin'] ?? null),
                $this->nullableInt($data['advertisedTjmMax'] ?? null),
                (string) ($data['location'] ?? ''), (string) ($data['remotePolicy'] ?? ''), $this->data->settings(), true
            );
        }
        $positioning = (new Positioning())->fill($data, $job, $cv);
        $this->em->persist($positioning); $this->em->flush();
        return new JsonResponse($positioning->toArray(), 201);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(Positioning $positioning, Request $request): JsonResponse
    {
        $positioning->fill($request->toArray()); $this->em->flush();
        return new JsonResponse($positioning->toArray());
    }
}
