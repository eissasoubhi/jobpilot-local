<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\RequiredPrimaryTechnologyGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:jobs:reconcile-pending-eligibility',
    description: 'Retire de la Review Queue les candidatures locales encore prêtes mais devenues inéligibles à cause d’une technologie principale obligatoire manquante.',
)]
final class ReconcileRecentJobEligibilityCommand extends Command
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

    private const LOCAL_PENDING_APPLICATION_STATUSES = [
        'DRAFT',
        'MISSING_CV',
        'READY_TO_SUBMIT',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequiredPrimaryTechnologyGuard $requiredTechnologyGuard,
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

        $jobs = $this->jobsWithPendingApplications();
        $rejectedJobs = 0;
        $removedFromQueue = 0;
        $protectedTerminal = 0;

        foreach ($jobs as $job) {
            if (!$job instanceof JobOffer) {
                continue;
            }

            $eligibility = $this->requiredTechnologyGuard->evaluate($job, $settings);
            if (!$eligibility['hardRejected']) {
                continue;
            }

            $reasons = $job->getScoreReasons();
            foreach ($eligibility['reasons'] as $reason) {
                if (!in_array($reason, $reasons, true)) {
                    $reasons[] = $reason;
                }
            }
            $score = $eligibility['scoreCap'] === null
                ? $job->getScore()
                : min($job->getScore(), $eligibility['scoreCap']);
            $job->refreshMatchingScore($score, $reasons);

            $applications = $this->em->getRepository(Application::class)->findBy(['jobOffer' => $job]);
            $hasTerminalApplication = false;

            foreach ($applications as $application) {
                if (!$application instanceof Application) {
                    continue;
                }

                if (in_array($application->getStatus(), self::TERMINAL_APPLICATION_STATUSES, true)) {
                    $hasTerminalApplication = true;
                    continue;
                }

                if (in_array($application->getStatus(), self::LOCAL_PENDING_APPLICATION_STATUSES, true)) {
                    $application->fill(['status' => 'IGNORED_NOT_MATCH']);
                    ++$removedFromQueue;
                }
            }

            if ($hasTerminalApplication) {
                ++$protectedTerminal;
                continue;
            }

            $job->fill(['status' => 'REJECTED_BY_FILTER']);
            ++$rejectedJobs;
        }

        $this->em->flush();

        $output->writeln(sprintf(
            '<info>Réconciliation terminée : %d offre(s) avec candidature locale en attente examinée(s), %d offre(s) rejetée(s), %d candidature(s) retirée(s) de la Review Queue, %d décision(s) terminale(s) protégée(s).</info>',
            count($jobs),
            $rejectedJobs,
            $removedFromQueue,
            $protectedTerminal,
        ));
        $output->writeln('La fenêtre d’un mois du recalcul de score reste inchangée ; cette commande protège toute la Review Queue encore en attente, quel que soit l’âge de l’offre.');
        $output->writeln('Aucune candidature soumise, en cours d’envoi ou déjà suivie n’est rétrogradée.');

        return Command::SUCCESS;
    }

    /** @return list<JobOffer> */
    private function jobsWithPendingApplications(): array
    {
        return $this->em->createQueryBuilder()
            ->select('DISTINCT job')
            ->from(Application::class, 'application')
            ->join('application.jobOffer', 'job')
            ->where('application.status IN (:statuses)')
            ->setParameter('statuses', self::LOCAL_PENDING_APPLICATION_STATUSES)
            ->getQuery()
            ->getResult();
    }
}
