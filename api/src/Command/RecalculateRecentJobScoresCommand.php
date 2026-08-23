<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\MatchingScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:jobs:recalculate-recent-scores',
    description: 'Recalcule les scores locaux des offres âgées de moins d’un mois sans modifier leur workflow.',
)]
final class RecalculateRecentJobScoresCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->em->getRepository(UserSettings::class)->findOneBy([]);
        if (!$settings instanceof UserSettings) {
            $output->writeln('<error>Aucun paramétrage candidat disponible.</error>');

            return Command::FAILURE;
        }

        $cutoff = (new \DateTimeImmutable())->sub(new \DateInterval('P1M'));
        $jobs = $this->em->createQueryBuilder()
            ->select('j')
            ->from(JobOffer::class, 'j')
            ->where('(j.publishedAt IS NOT NULL AND j.publishedAt >= :cutoff)')
            ->orWhere('(j.publishedAt IS NULL AND j.discoveredAt >= :cutoff)')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('j.publishedAt', 'DESC')
            ->addOrderBy('j.discoveredAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Deliberately use the deterministic local scorer. Existing AI-scored offers
        // are kept intact so this maintenance operation cannot unexpectedly consume
        // provider quota or replace an AI evaluation with a fallback score.
        $localScorer = new MatchingScoreService();
        $updated = 0;
        $unchanged = 0;
        $skippedAi = 0;

        foreach ($jobs as $job) {
            if (!$job instanceof JobOffer) {
                continue;
            }

            if ($this->hasAiEvaluation($job)) {
                ++$skippedAi;
                continue;
            }

            $evaluation = $localScorer->evaluate($job, $settings);
            $score = (int) $evaluation['score'];
            $reasons = array_values($evaluation['reasons']);

            if ($job->getScore() === $score && $job->getScoreReasons() === $reasons) {
                ++$unchanged;
                continue;
            }

            $job->refreshMatchingScore($score, $reasons);
            ++$updated;
        }

        $this->em->flush();

        $output->writeln(sprintf(
            '<info>Recalcul terminé : %d offre(s) récente(s), %d mise(s) à jour, %d inchangée(s), %d score(s) IA conservé(s).</info>',
            count($jobs),
            $updated,
            $unchanged,
            $skippedAi,
        ));
        $output->writeln(sprintf('Fenêtre : depuis le %s (un mois glissant).', $cutoff->format('Y-m-d H:i:s')));
        $output->writeln('Les statuts, décisions, CV, messages et montants proposés ne sont pas modifiés.');

        return Command::SUCCESS;
    }

    private function hasAiEvaluation(JobOffer $job): bool
    {
        foreach ($job->getScoreReasons() as $reason) {
            if (str_starts_with($reason, 'Analyse IA :')) {
                return true;
            }
        }

        return false;
    }
}
