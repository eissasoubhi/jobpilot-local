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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController
{
    public function __construct(private EntityManagerInterface $em) {}

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

        /** @var list<JobOffer> $recentActivityJobs */
        $recentActivityJobs = $jobs->createQueryBuilder('job')
            ->andWhere('job.discoveredAt >= :start')
            ->setParameter('start', $activityStart)
            ->getQuery()
            ->getResult();

        /** @var list<Application> $recentSubmissions */
        $recentSubmissions = $apps->createQueryBuilder('application')
            ->andWhere('application.submittedAt IS NOT NULL')
            ->andWhere('application.submittedAt >= :start')
            ->setParameter('start', $activityStart)
            ->getQuery()
            ->getResult();

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

        return new JsonResponse([
            'period' => [
                'days' => 7,
                'from' => $activityStart->format('Y-m-d'),
                'to' => $today->format('Y-m-d'),
            ],
            'counts' => [
                'jobs' => $jobCount,
                'newJobs' => count($recentActivityJobs),
                'qualifiedJobs' => $qualifiedJobs,
                'applications' => $applicationCount,
                'prepared' => $readyToSubmit,
                'submitted' => $submitted,
                'submittedRecently' => count($recentSubmissions),
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
            ],
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
}
