<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\JobOffer;
use App\JobCatalog\Application\CanonicalJobOfferService;
use App\Service\ApplicationPreparationService;
use App\Service\JobPriorityScoreService;
use App\Service\JobProcessor;
use App\Service\LocalDataService;
use App\Service\SourceConversionReportService;
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
        private CanonicalJobOfferService $canonicalJobs,
        private ApplicationPreparationService $preparation,
        private JobPriorityScoreService $priorityScore,
        private SourceConversionReportService $conversionReport,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $jobs = $this->em->getRepository(JobOffer::class)->findAll();
        $profile = $this->data->profile();
        $sourcePerformance = $this->sourcePerformance();

        $ranked = array_map(function (JobOffer $job) use ($profile, $sourcePerformance): array {
            $priority = $this->priorityScore->evaluate($job, $profile, $sourcePerformance);
            $payload = $job->toArray();
            $payload['priorityScore'] = $priority['score'];
            $payload['priorityReasons'] = $priority['reasons'];
            $payload['priorityComponents'] = $priority['components'];

            return [
                'job' => $job,
                'payload' => $payload,
                'priority' => $priority['score'],
            ];
        }, $jobs);

        usort($ranked, static function (array $a, array $b): int {
            $priorityOrder = $b['priority'] <=> $a['priority'];
            if ($priorityOrder !== 0) {
                return $priorityOrder;
            }

            /** @var JobOffer $aJob */
            $aJob = $a['job'];
            /** @var JobOffer $bJob */
            $bJob = $b['job'];

            $matchOrder = $bJob->getScore() <=> $aJob->getScore();
            if ($matchOrder !== 0) {
                return $matchOrder;
            }

            return ($bJob->getPublishedAt()?->getTimestamp() ?? 0)
                <=> ($aJob->getPublishedAt()?->getTimestamp() ?? 0);
        });

        return new JsonResponse(array_column($ranked, 'payload'));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(JobOffer $job): JsonResponse
    {
        $priority = $this->priorityScore->evaluate($job, $this->data->profile(), $this->sourcePerformance());
        $payload = $job->toArray();
        $payload['priorityScore'] = $priority['score'];
        $payload['priorityReasons'] = $priority['reasons'];
        $payload['priorityComponents'] = $priority['components'];

        return new JsonResponse($payload);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $sourceName = trim((string) ($payload['source'] ?? 'Manuel')) ?: 'Manuel';
        $sourceCode = trim((string) ($payload['sourceCode'] ?? 'manual')) ?: 'manual';
        $result = $this->canonicalJobs->import(
            $payload,
            $sourceCode,
            $sourceName,
            'MANUAL',
            $this->data->settings(),
            $this->data->profile(),
        );

        return new JsonResponse($result->job()->toArray(), $result->isImported() ? 201 : 200);
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
        $this->em->remove($job);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    /** @return array<string, array<string, int|string|float|null>> */
    private function sourcePerformance(): array
    {
        $report = $this->conversionReport->report();
        $performance = [];

        foreach ($report['sources'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = strtolower(trim((string) ($row['code'] ?? '')));
            if ($code !== '') {
                $performance[$code] = $row;
            }
        }

        return $performance;
    }
}
