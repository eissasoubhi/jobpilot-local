<?php
namespace App\Controller;

use App\Entity\JobOffer;
use App\Service\ApplicationPreparationService;
use App\Service\JobProcessor;
use App\Service\LocalDataService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/jobs')]
final class JobController
{
    public function __construct(
        private EntityManagerInterface $em,
        private LocalDataService $data,
        private JobProcessor $processor,
        private ApplicationPreparationService $preparation,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $jobs = $this->em->getRepository(JobOffer::class)->findAll();
        usort($jobs, static function (JobOffer $a, JobOffer $b): int {
            $aBucket = self::freshnessBucket($a->getPublishedAt());
            $bBucket = self::freshnessBucket($b->getPublishedAt());
            return $aBucket === $bBucket ? $b->getScore() <=> $a->getScore() : $aBucket <=> $bBucket;
        });
        return new JsonResponse(array_map(static fn(JobOffer $job) => $job->toArray(), $jobs));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(JobOffer $job): JsonResponse { return new JsonResponse($job->toArray()); }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $job = (new JobOffer())->fill($request->toArray());
        if ($job->getTitle() === '' || $job->getDescription() === '') throw new \InvalidArgumentException('Le titre et la description sont obligatoires.');
        $this->processor->process($job, $this->data->settings(), $this->data->profile());
        return new JsonResponse($job->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(JobOffer $job, Request $request): JsonResponse
    {
        $job->fill($request->toArray());
        $this->processor->process($job, $this->data->settings(), $this->data->profile());
        return new JsonResponse($job->toArray());
    }

    #[Route('/{id}/prepare', methods: ['POST'])]
    public function prepare(JobOffer $job): JsonResponse
    {
        $application = $this->preparation->prepare($job, $this->data->profile());
        return new JsonResponse($application->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(JobOffer $job): JsonResponse
    {
        $this->em->remove($job); $this->em->flush();
        return new JsonResponse(null, 204);
    }

    private static function freshnessBucket(?\DateTimeImmutable $date): int
    {
        if ($date === null) return 5;
        $hours = max(0, (time() - $date->getTimestamp()) / 3600);
        return match (true) { $hours < 24 => 0, $hours < 72 => 1, $hours < 168 => 2, $hours < 336 => 3, default => 4 };
    }
}
