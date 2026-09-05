<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Application;
use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Http\ApiPagination;
use App\JobCatalog\Application\CanonicalJobOfferService;
use App\Service\ApplicationPreparationService;
use App\Service\JobPriorityScoreService;
use App\Service\JobProcessor;
use App\Service\JobRankingOrderService;
use App\Service\JobReactionPreferenceScoreService;
use App\Service\LocalDataService;
use App\Service\SearchPreferenceMatcher;
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
        private JobRankingOrderService $rankingOrder,
        private JobReactionPreferenceScoreService $reactionPreferences,
        private SearchPreferenceMatcher $searchPreferences,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = trim((string) $request->query->get('status', ''));
        $query = $this->em->getRepository(JobOffer::class)->createQueryBuilder('job')
            ->leftJoin('job.occurrences', 'occurrence')
            ->addSelect('occurrence')
            ->leftJoin('job.recommendedCv', 'recommendedCv')
            ->addSelect('recommendedCv');
        if ($status !== '') {
            $query
                ->andWhere('job.status = :status')
                ->setParameter('status', $status);
        }
        $jobs = $query
            ->getQuery()
            ->getResult();
        $applications = $this->em->getRepository(Application::class)->findAll();
        $profile = $this->data->profile();

        $jobs = array_values(array_filter(
            $jobs,
            fn (JobOffer $job): bool => $this->searchPreferences->evaluate($job, $profile)['eligible'],
        ));

        $sourcePerformance = $this->sourcePerformance();
        $reactions = $this->reactionPreferences->evaluateMany($jobs, $applications);

        $ranked = array_map(function (JobOffer $job) use ($profile, $sourcePerformance, $applications, $reactions): array {
            $priority = $this->adaptivePriority(
                $job,
                $profile,
                $sourcePerformance,
                $applications,
                $reactions[spl_object_id($job)] ?? null,
            );
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

        usort($ranked, function (array $a, array $b): int {
            /** @var JobOffer $aJob */
            $aJob = $a['job'];
            /** @var JobOffer $bJob */
            $bJob = $b['job'];

            return $this->rankingOrder->compare($aJob, (int) $a['priority'], $bJob, (int) $b['priority']);
        });

        $payloads = array_column($ranked, 'payload');
        $pagination = ApiPagination::fromRequest($request);
        if ($pagination === null) {
            return new JsonResponse($payloads);
        }

        return new JsonResponse([
            'items' => array_slice($payloads, $pagination->offset(), $pagination->limit),
            'pagination' => $pagination->metadata(count($payloads)),
        ]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(JobOffer $job): JsonResponse
    {
        $priority = $this->adaptivePriority(
            $job,
            $this->data->profile(),
            $this->sourcePerformance(),
            $this->em->getRepository(Application::class)->findAll(),
        );
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

    /**
     * @param array<string, array<string, int|string|float|null>> $sourcePerformance
     * @param iterable<Application> $applications
     * @param array{score:int, adjustment:int, evidence:int, similarityWeight:float}|null $reaction
     * @return array{score:int,reasons:list<string>,components:array<string,int>}
     */
    private function adaptivePriority(
        JobOffer $job,
        CandidateProfile $profile,
        array $sourcePerformance,
        iterable $applications,
        ?array $reaction = null,
    ): array {
        $priority = $this->priorityScore->evaluate($job, $profile, $sourcePerformance);
        $reaction ??= $this->reactionPreferences->evaluate($job, $applications);
        $adjustment = $job->getStatus() === 'REJECTED_BY_FILTER' ? 0 : $reaction['adjustment'];

        $priority['score'] = max(0, min(100, $priority['score'] + $adjustment));
        $priority['components']['reactions'] = $reaction['score'];
        $priority['reasons'][] = $reaction['evidence'] === 0
            ? 'Apprentissage selon tes décisions : 50/100 · neutre (pas encore de signal similaire).'
            : sprintf(
                'Apprentissage selon tes décisions : %d/100 · %d signal(s) similaire(s) · ajustement %+d.',
                $reaction['score'],
                $reaction['evidence'],
                $adjustment,
            );

        return $priority;
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
            $code = strtolower(trim((string) ($row['code'] ?? ''));
            if ($code !== '') {
                $performance[$code] = $row;
            }
        }

        return $performance;
    }
}
