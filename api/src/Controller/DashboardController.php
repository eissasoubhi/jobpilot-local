<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Application;
use App\Entity\CrmFollowUpTask;
use App\Entity\InboxMessage;
use App\Entity\JobOffer;
use App\Entity\Positioning;
use App\Entity\SourceConnector;
use App\Entity\UserSettings;
use App\Service\SourceConversionReportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SourceConversionReportService $sourceConversionReport,
    ) {}

    #[Route('/api/dashboard', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $jobs = $this->em->getRepository(JobOffer::class);
        $apps = $this->em->getRepository(Application::class);
        $positions = $this->em->getRepository(Positioning::class);
        $messages = $this->em->getRepository(InboxMessage::class);
        $followUps = $this->em->getRepository(CrmFollowUpTask::class);
        $settings = $this->em->getRepository(UserSettings::class)->findOneBy([]) ?? new UserSettings();

        $today = new \DateTimeImmutable('today');
        $activityStart = $today->modify('-6 days');
        $previousStart = $activityStart->modify('-7 days');

        /** @var list<JobOffer> $recentActivityJobs */
        $recentActivityJobs = $jobs->createQueryBuilder('job')
            ->andWhere('job.discoveredAt >= :start')
            ->setParameter('start', $activityStart)
            ->getQuery()
            ->getResult();

        $previousJobs = (int) $jobs->createQueryBuilder('job')
            ->select('COUNT(job.id)')
            ->andWhere('job.discoveredAt >= :previousStart')
            ->andWhere('job.discoveredAt < :currentStart')
            ->setParameter('previousStart', $previousStart)
            ->setParameter('currentStart', $activityStart)
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<Application> $recentSubmissions */
        $recentSubmissions = $apps->createQueryBuilder('application')
            ->andWhere('application.submittedAt IS NOT NULL')
            ->andWhere('application.submittedAt >= :start')
            ->setParameter('start', $activityStart)
            ->getQuery()
            ->getResult();

        $previousSubmissions = (int) $apps->createQueryBuilder('application')
            ->select('COUNT(application.id)')
            ->andWhere('application.submittedAt IS NOT NULL')
            ->andWhere('application.submittedAt >= :previousStart')
            ->andWhere('application.submittedAt < :currentStart')
            ->setParameter('previousStart', $previousStart)
            ->setParameter('currentStart', $activityStart)
            ->getQuery()
            ->getSingleScalarResult();

        $submitted = (int) $apps->createQueryBuilder('application')
            ->select('COUNT(application.id)')
            ->andWhere('application.submittedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $responseStatuses = ['RESPONSE_RECEIVED', 'INFORMATION_REQUESTED', 'INTERVIEW', 'REJECTED'];
        $responses = (int) $apps->createQueryBuilder('application')
            ->select('COUNT(application.id)')
            ->andWhere('application.status IN (:statuses)')
            ->setParameter('statuses', $responseStatuses)
            ->getQuery()
            ->getSingleScalarResult();

        $interviews = $apps->count(['status' => 'INTERVIEW']);
        $rejected = $apps->count(['status' => 'REJECTED']);
        $readyToSubmit = $apps->count(['status' => 'READY_TO_SUBMIT']);
        $missingCv = $apps->count(['status' => 'MISSING_CV']);
        $failedSubmissions = $apps->count(['status' => 'SUBMISSION_FAILED']);

        $qualifiedJobs = (int) $jobs->createQueryBuilder('job')
            ->select('COUNT(job.id)')
            ->andWhere('job.score >= :threshold')
            ->setParameter('threshold', $settings->getMatchingThreshold())
            ->getQuery()
            ->getSingleScalarResult();

        $averageScoreValue = $jobs->createQueryBuilder('job')
            ->select('AVG(job.score)')
            ->andWhere('job.score > 0')
            ->getQuery()
            ->getSingleScalarResult();
        $averageScore = $averageScoreValue === null ? 0 : (int) round((float) $averageScoreValue);

        $actionMessages = $messages->count(['actionRequired' => true, 'processed' => false]);
        $followUpsDue = (int) $followUps->createQueryBuilder('followUp')
            ->select('COUNT(followUp.id)')
            ->andWhere('followUp.completedAt IS NULL')
            ->andWhere('followUp.dueAt <= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<SourceConnector> $connectorItems */
        $connectorItems = $this->em->getRepository(SourceConnector::class)->findAll();
        $operationalConnectors = 0;
        $connectorsNeedingAttention = 0;
        $lastConnectorSyncAt = null;
        foreach ($connectorItems as $connector) {
            if ($connector->canSynchronize()) {
                ++$operationalConnectors;
            } elseif ($connector->isEnabled()) {
                ++$connectorsNeedingAttention;
            }

            $lastSyncedAt = $connector->getLastSyncedAt();
            if ($lastSyncedAt !== null && ($lastConnectorSyncAt === null || $lastSyncedAt > $lastConnectorSyncAt)) {
                $lastConnectorSyncAt = $lastSyncedAt;
            }
        }

        $trend = [];
        for ($offset = 0; $offset < 7; ++$offset) {
            $date = $activityStart->modify(sprintf('+%d days', $offset));
            $key = $date->format('Y-m-d');
            $trend[$key] = [
                'date' => $key,
                'jobs' => 0,
                'submitted' => 0,
            ];
        }

        foreach ($recentActivityJobs as $job) {
            $key = $job->getDiscoveredAt()->format('Y-m-d');
            if (isset($trend[$key])) {
                ++$trend[$key]['jobs'];
            }
        }

        foreach ($recentSubmissions as $application) {
            $key = $application->getSubmittedAt()?->format('Y-m-d');
            if ($key !== null && isset($trend[$key])) {
                ++$trend[$key]['submitted'];
            }
        }

        $applicationCount = $apps->count([]);
        $jobCount = $jobs->count([]);
        $recent = $jobs->findBy([], ['discoveredAt' => 'DESC'], 5);
        $currentJobs = count($recentActivityJobs);
        $currentSubmissions = count($recentSubmissions);
        $sourcePerformance = $this->sourcePerformance();
        $responseLatency = $this->responseLatency();

        return new JsonResponse([
            'period' => [
                'days' => 7,
                'from' => $activityStart->format('Y-m-d'),
                'to' => $today->format('Y-m-d'),
            ],
            'comparison' => [
                'newJobs' => $this->comparison($currentJobs, $previousJobs),
                'submitted' => $this->comparison($currentSubmissions, $previousSubmissions),
            ],
            'counts' => [
                'jobs' => $jobCount,
                'newJobs' => $currentJobs,
                'qualifiedJobs' => $qualifiedJobs,
                'applications' => $applicationCount,
                'prepared' => $readyToSubmit,
                'submitted' => $submitted,
                'submittedRecently' => $currentSubmissions,
                'interviews' => $interviews,
                'rejected' => $rejected,
                'positionings' => $positions->count([]),
                'messages' => $messages->count([]),
                'actionMessages' => $actionMessages,
                'followUpsDue' => $followUpsDue,
                'missingCv' => $missingCv,
                'failedSubmissions' => $failedSubmissions,
            ],
            'performance' => [
                'responseRate' => $submitted > 0 ? round(($responses / $submitted) * 100, 1) : 0,
                'interviewRate' => $submitted > 0 ? round(($interviews / $submitted) * 100, 1) : 0,
                'responses' => $responses,
                'averageScore' => $averageScore,
                'firstResponseMedianHours' => $responseLatency['medianHours'],
                'firstResponseMeasured' => $responseLatency['measured'],
            ],
            'sourcePerformance' => $sourcePerformance,
            'pipeline' => [
                ['key' => 'detected', 'label' => 'Offres détectées', 'value' => $jobCount],
                ['key' => 'qualified', 'label' => sprintf('Score ≥ %d', $settings->getMatchingThreshold()), 'value' => $qualifiedJobs],
                ['key' => 'prepared', 'label' => 'Candidatures préparées', 'value' => $applicationCount],
                ['key' => 'submitted', 'label' => 'Envoyées', 'value' => $submitted],
                ['key' => 'interviews', 'label' => 'Entretiens', 'value' => $interviews],
            ],
            'trend' => array_values($trend),
            'automation' => [
                'matchingThreshold' => $settings->getMatchingThreshold(),
                'autoPrepare' => $settings->isAutoPrepare(),
                'autoSubmitEnabled' => $settings->isAutoSubmitEnabled(),
                'autoSubmitThreshold' => $settings->getAutoSubmitThreshold(),
                'autoSubmitDailyLimit' => $settings->getAutoSubmitDailyLimit(),
                'targetJobsCount' => count($settings->getTargetJobs()),
            ],
            'connectors' => [
                'total' => count($connectorItems),
                'operational' => $operationalConnectors,
                'needsAttention' => $connectorsNeedingAttention,
                'lastSyncedAt' => $lastConnectorSyncAt?->format(DATE_ATOM),
            ],
            'recentJobs' => array_map(static fn (JobOffer $job): array => $job->toArray(), $recent),
        ]);
    }

    /** @return array{current: int, previous: int, deltaPercent: float|null} */
    private function comparison(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'current' => $current,
                'previous' => 0,
                'deltaPercent' => $current === 0 ? 0.0 : null,
            ];
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'deltaPercent' => round((($current - $previous) / $previous) * 100, 1),
        ];
    }

    /** @return array{measured: int, medianHours: float|null} */
    private function responseLatency(): array
    {
        $categories = ['APPLICATION_REPLY', 'INFORMATION_REQUEST', 'INTERVIEW_REQUEST', 'REJECTION'];

        /** @var list<InboxMessage> $responseMessages */
        $responseMessages = $this->em->getRepository(InboxMessage::class)->createQueryBuilder('message')
            ->addSelect('application')
            ->innerJoin('message.application', 'application')
            ->andWhere('message.category IN (:categories)')
            ->andWhere('application.submittedAt IS NOT NULL')
            ->setParameter('categories', $categories)
            ->orderBy('message.receivedAt', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var array<int, float> $firstResponseHours */
        $firstResponseHours = [];
        foreach ($responseMessages as $message) {
            $application = $message->getApplication();
            $applicationId = $application?->getId();
            $submittedAt = $application?->getSubmittedAt();
            if ($applicationId === null || $submittedAt === null || isset($firstResponseHours[$applicationId])) {
                continue;
            }

            $receivedAt = $message->getReceivedAt();
            if ($receivedAt < $submittedAt) {
                continue;
            }

            $firstResponseHours[$applicationId] = ($receivedAt->getTimestamp() - $submittedAt->getTimestamp()) / 3600;
        }

        $durations = array_values($firstResponseHours);
        sort($durations, SORT_NUMERIC);
        $count = count($durations);
        if ($count === 0) {
            return ['measured' => 0, 'medianHours' => null];
        }

        $middle = intdiv($count, 2);
        $median = $count % 2 === 1
            ? $durations[$middle]
            : ($durations[$middle - 1] + $durations[$middle]) / 2;

        return [
            'measured' => $count,
            'medianHours' => round($median, 1),
        ];
    }

    /** @return array{trackedSources: int, leaders: list<array{code: string, name: string, submitted: int, responses: int, interviews: int, responseRate: float, interviewRate: float, averageMatchingScore: float, lowVolume: bool}>} */
    private function sourcePerformance(): array
    {
        $report = $this->sourceConversionReport->report();
        $trackedRows = array_values(array_filter(
            $report['sources'],
            static fn (array $row): bool => (int) ($row['applications'] ?? 0) > 0,
        ));
        $rows = array_values(array_filter(
            $trackedRows,
            static fn (array $row): bool => (int) ($row['submitted'] ?? 0) > 0,
        ));

        usort($rows, static function (array $left, array $right): int {
            foreach (['submitted', 'responses', 'interviews', 'applications', 'offers'] as $metric) {
                $comparison = (int) ($right[$metric] ?? 0) <=> (int) ($left[$metric] ?? 0);
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        $leaders = array_map(static fn (array $row): array => [
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'submitted' => (int) ($row['submitted'] ?? 0),
            'responses' => (int) ($row['responses'] ?? 0),
            'interviews' => (int) ($row['interviews'] ?? 0),
            'responseRate' => (float) ($row['responseRate'] ?? 0),
            'interviewRate' => (float) ($row['interviewRate'] ?? 0),
            'averageMatchingScore' => (float) ($row['averageMatchingScore'] ?? 0),
            'lowVolume' => (int) ($row['submitted'] ?? 0) < 3,
        ], array_slice($rows, 0, 3));

        return [
            'trackedSources' => count($trackedRows),
            'leaders' => $leaders,
        ];
    }
}
