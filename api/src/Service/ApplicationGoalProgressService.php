<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JobTimelineEvent;
use App\Timeline\JobTimelineEventType;
use Doctrine\ORM\EntityManagerInterface;

final class ApplicationGoalProgressService
{
    private const ALREADY_APPLIED_CHANNEL = 'Candidature externe';

    public function __construct(
        private EntityManagerInterface $em,
        private LocalDataService $data,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(?\DateTimeImmutable $now = null): array
    {
        $config = $this->data->settings()->getApplicationGoals();
        $timezone = new \DateTimeZone($config['timezone']);
        $localNow = ($now ?? new \DateTimeImmutable('now', $timezone))->setTimezone($timezone);
        $dayStart = $localNow->setTime(0, 0);
        $weekStart = $dayStart->modify('monday this week');
        $monthStart = $dayStart->modify('first day of this month');

        $periods = [
            'daily' => $this->period(
                'daily',
                'Aujourd’hui',
                $config['daily'],
                $dayStart,
                $dayStart->modify('+1 day'),
            ),
            'weekly' => $this->period(
                'weekly',
                'Cette semaine',
                $config['weekly'],
                $weekStart,
                $weekStart->modify('+1 week'),
            ),
            'monthly' => $this->period(
                'monthly',
                'Ce mois',
                $config['monthly'],
                $monthStart,
                $monthStart->modify('first day of next month'),
            ),
        ];

        $startedAt = $config['startedAt'] !== null
            ? new \DateTimeImmutable($config['startedAt'])
            : null;

        $missed = [];
        $previousPeriods = [
            ['daily', 'Objectif d’hier manqué', $config['daily'], $dayStart->modify('-1 day'), $dayStart],
            ['weekly', 'Objectif de la semaine dernière manqué', $config['weekly'], $weekStart->modify('-1 week'), $weekStart],
            ['monthly', 'Objectif du mois dernier manqué', $config['monthly'], $monthStart->modify('-1 month'), $monthStart],
        ];

        foreach ($previousPeriods as [$key, $label, $target, $start, $end]) {
            if ($target <= 0 || $startedAt === null || $startedAt > $start) {
                continue;
            }

            $achieved = $this->countSubmitted($start, $end);
            if ($achieved >= $target) {
                continue;
            }

            $missed[] = [
                'period' => $key,
                'label' => $label,
                'target' => $target,
                'achieved' => $achieved,
                'remaining' => $target - $achieved,
                'start' => $start->format(DATE_ATOM),
                'end' => $end->format(DATE_ATOM),
            ];
        }

        return [
            'config' => $config,
            'periods' => $periods,
            'missed' => $missed,
            'generatedAt' => $localNow->format(DATE_ATOM),
        ];
    }

    /** @return array<string, int|string|bool> */
    private function period(
        string $key,
        string $label,
        int $target,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): array {
        $achieved = $target > 0 ? $this->countSubmitted($start, $end) : 0;
        $remaining = max(0, $target - $achieved);
        $percent = $target > 0 ? (int) round(($achieved / $target) * 100) : 0;

        return [
            'period' => $key,
            'label' => $label,
            'enabled' => $target > 0,
            'target' => $target,
            'achieved' => $achieved,
            'remaining' => $remaining,
            'percent' => $percent,
            'completed' => $target > 0 && $achieved >= $target,
            'start' => $start->format(DATE_ATOM),
            'end' => $end->format(DATE_ATOM),
        ];
    }

    private function countSubmitted(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $utc = new \DateTimeZone('UTC');
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(event.application) AS applicationId')
            ->from(JobTimelineEvent::class, 'event')
            ->innerJoin('event.application', 'application')
            ->andWhere('event.type = :type')
            ->andWhere('application.submittedAt IS NOT NULL')
            ->andWhere('application.channel <> :alreadyAppliedChannel')
            ->andWhere('event.occurredAt >= :start')
            ->andWhere('event.occurredAt < :end')
            ->setParameter('type', JobTimelineEventType::APPLICATION_SUBMITTED)
            ->setParameter('alreadyAppliedChannel', self::ALREADY_APPLIED_CHANNEL)
            ->setParameter('start', $start->setTimezone($utc))
            ->setParameter('end', $end->setTimezone($utc))
            ->getQuery()
            ->getScalarResult();

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['applicationId'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        return count($ids);
    }
}
