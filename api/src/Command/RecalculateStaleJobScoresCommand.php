<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\JobOfferMatchingScoreState;
use App\Entity\UserSettings;
use App\Service\MatchingScoreService;
use App\Service\MatchingScoreVersion;
use App\Service\MatchingScoreVersionStore;
use App\Service\RequiredPrimaryTechnologyGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:jobs:recalculate-stale-scores',
    description: 'Recalcule localement les scores persistés dont la version du scorer est ancienne, sans toucher aux décisions terminales.',
)]
final class RecalculateStaleJobScoresCommand extends Command
{
    private const TERMINAL_APPLICATION_STATUSES = [
        'SUBMITTED',
        'SUBMISSION_PENDING',
        'APPLICATION_CONFIRMED',
        'RESPONSE_RECEIVED',
        'INFORMATION_REQUESTED',
        'INTERVIEW',
        'REJECTED',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequiredPrimaryTechnologyGuard $requiredTechnologyGuard,
        private readonly MatchingScoreVersionStore $matchingScoreVersionStore,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->em->getRepository(UserSettings::class)->findOneBy([]);
        if (!$settings instanceof UserSettings) {
            $output->writeln('<error>Aucun paramétrage candidat disponible.</error>');

            return Command::FAILURE;
        }

        $jobs = $this->staleJobs();
        $localScorer = new MatchingScoreService();
        $updated = 0;
        $unchanged = 0;
        $preservedAi = 0;
        $protectedTerminal = 0;

        foreach ($jobs as $job) {
            if (!$job instanceof JobOffer) {
                continue;
            }

            if ($this->hasTerminalApplication($job)) {
                ++$protectedTerminal;
                continue;
            }

            if ($this->hasAiEvaluation($job)) {
                // This version tracks the deterministic matching algorithm. Existing
                // provider-backed evaluations are not replaced or re-triggered here.
                $this->matchingScoreVersionStore->mark($job);
                ++$preservedAi;
                continue;
            }

            $evaluation = $localScorer->evaluate($job, $settings);
            $score = (int) $evaluation['score'];
            $reasons = array_values($evaluation['reasons']);
            $requiredTechnology = $this->requiredTechnologyGuard->evaluate($job, $settings);

            if ($requiredTechnology['hardRejected']) {
                if ($requiredTechnology['scoreCap'] !== null) {
                    $score = min($score, $requiredTechnology['scoreCap']);
                    $capReason = sprintf('Score plafonné à %d/100 : technologie principale obligatoire manquante.', $requiredTechnology['scoreCap']);
                    if (!in_array($capReason, $reasons, true)) {
                        $reasons[] = $capReason;
                    }
                }
                foreach ($requiredTechnology['reasons'] as $reason) {
                    if (!in_array($reason, $reasons, true)) {
                        $reasons[] = $reason;
                    }
                }
            }

            if ($job->getScore() === $score && $job->getScoreReasons() === $reasons) {
                $this->matchingScoreVersionStore->mark($job);
                ++$unchanged;
                continue;
            }

            $job->refreshMatchingScore($score, $reasons);
            $this->matchingScoreVersionStore->mark($job);
            ++$updated;
        }

        $this->em->flush();

        $output->writeln(sprintf(
            '<info>Version matching %d : %d score(s) stale(s), %d recalculé(s), %d inchangé(s), %d évaluation(s) IA conservée(s), %d décision(s) terminale(s) protégée(s).</info>',
            MatchingScoreVersion::CURRENT,
            count($jobs),
            $updated,
            $unchanged,
            $preservedAi,
            $protectedTerminal,
        ));
        $output->writeln('Aucun envoi externe, statut de candidature, CV, message ou montant proposé n’est modifié.');

        return Command::SUCCESS;
    }

    /** @return list<JobOffer> */
    private function staleJobs(): array
    {
        return $this->em->createQueryBuilder()
            ->select('job')
            ->from(JobOffer::class, 'job')
            ->leftJoin(
                JobOfferMatchingScoreState::class,
                'scoreState',
                'WITH',
                'scoreState.jobOffer = job',
            )
            ->where('scoreState.id IS NULL OR scoreState.version < :currentVersion')
            ->setParameter('currentVersion', MatchingScoreVersion::CURRENT)
            ->orderBy('job.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function hasTerminalApplication(JobOffer $job): bool
    {
        $count = $this->em->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->select('COUNT(application.id)')
            ->where('application.jobOffer = :job')
            ->andWhere('application.status IN (:statuses)')
            ->setParameter('job', $job)
            ->setParameter('statuses', self::TERMINAL_APPLICATION_STATUSES)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    private function hasAiEvaluation(JobOffer $job): bool
    {
        foreach ($job->getScoreReasons() as $reason) {
            if (is_string($reason) && str_starts_with($reason, 'Analyse IA :')) {
                return true;
            }
        }

        return false;
    }
}
