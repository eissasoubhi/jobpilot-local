<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\MarketSkillsReportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardMarketSkillsController
{
    public function __construct(
        private EntityManagerInterface $em,
        private MarketSkillsReportService $marketSkills,
    ) {}

    #[Route('/api/dashboard/market-skills', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $settings = $this->em->getRepository(UserSettings::class)->findOneBy([]) ?? new UserSettings();
        $threshold = $settings->getMatchingThreshold();
        $periodDays = 30;
        $start = (new \DateTimeImmutable('today'))->modify(sprintf('-%d days', $periodDays - 1));

        /** @var list<JobOffer> $jobs */
        $jobs = $this->em->getRepository(JobOffer::class)->createQueryBuilder('job')
            ->andWhere('job.discoveredAt >= :start')
            ->andWhere('job.score >= :threshold')
            ->setParameter('start', $start)
            ->setParameter('threshold', $threshold)
            ->orderBy('job.discoveredAt', 'DESC')
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'periodDays' => $periodDays,
            'matchingThreshold' => $threshold,
            ...$this->marketSkills->summarize($jobs, $settings),
        ]);
    }
}
