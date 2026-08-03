<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Application;
use App\Service\AutomaticSubmissionService;
use App\Service\LocalDataService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:applications:auto-submit',
    description: 'Envoie les candidatures éligibles par Gmail lorsque l’automatisation est activée.',
)]
final class AutomaticSubmissionCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private LocalDataService $data,
        private AutomaticSubmissionService $submission,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $settings = $this->data->settings();

        if (!$settings->isAutoSubmitEnabled()) {
            $io->note('Envoi automatique désactivé.');
            return Command::SUCCESS;
        }

        $applications = $this->em->getRepository(Application::class)->findBy(
            ['status' => 'READY_TO_SUBMIT'],
            ['createdAt' => 'ASC'],
        );

        $submitted = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($applications as $application) {
            $result = $this->submission->submitIfEligible($application, $settings);

            if ($result['status'] === 'submitted') {
                ++$submitted;
                continue;
            }

            if ($result['status'] === 'failed') {
                ++$failed;
                $io->warning(sprintf(
                    'Échec pour la candidature #%d : %s',
                    $application->getId(),
                    $result['reason'] ?? 'erreur inconnue',
                ));
                continue;
            }

            ++$skipped;
        }

        $io->success(sprintf(
            '%d envoyée(s), %d échec(s), %d ignorée(s).',
            $submitted,
            $failed,
            $skipped,
        ));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
