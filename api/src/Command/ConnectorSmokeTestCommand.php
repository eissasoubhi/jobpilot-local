<?php

declare(strict_types=1);

namespace App\Command;

use App\JobDiscovery\Application\ConnectorSmokeResultEvaluator;
use App\Service\JobSearchSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:connectors:smoke-test',
    description: 'Exécute volontairement un smoke test réel d’un connecteur autorisé.',
)]
final class ConnectorSmokeTestCommand extends Command
{
    public function __construct(
        private JobSearchSyncService $syncService,
        private ConnectorSmokeResultEvaluator $evaluator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('connector', InputArgument::REQUIRED, 'Code du connecteur à tester, par exemple le-studio-tech.')
            ->addOption(
                'live',
                null,
                InputOption::VALUE_NONE,
                'Confirme explicitement que le test peut contacter la source réelle et alimenter le catalogue canonique.',
            )
            ->addOption(
                'allow-zero',
                null,
                InputOption::VALUE_NONE,
                'Considère zéro offre comme un résultat valide. À utiliser uniquement lorsque zéro est réellement attendu.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connectorCode = strtolower(trim((string) $input->getArgument('connector')));
        if ($connectorCode === '') {
            $output->writeln('<error>Le code du connecteur est obligatoire.</error>');

            return Command::INVALID;
        }

        if ($input->getOption('live') !== true) {
            $output->writeln('<error>Smoke test non exécuté : l’option --live est obligatoire.</error>');
            $output->writeln('Cette protection empêche tout appel réseau réel accidentel.');

            return Command::INVALID;
        }

        if ($this->runningInCi()) {
            $output->writeln('<error>Smoke test réel interdit dans un environnement CI/GitHub Actions.</error>');
            $output->writeln('Utiliser uniquement les fixtures locales et les transports mockés dans la CI.');

            return Command::INVALID;
        }

        $output->writeln(sprintf('<info>Smoke test réel du connecteur %s…</info>', $connectorCode));
        $output->writeln('Le même pipeline que la synchronisation normale sera utilisé : collecte, déduplication, filtre profil et import canonique.');

        try {
            $result = $this->syncService->sync(true, $connectorCode, 'smoke');
        } catch (\Throwable $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');

            return Command::FAILURE;
        }

        $assessment = $this->evaluator->evaluate(
            $result,
            $connectorCode,
            $input->getOption('allow-zero') === true,
        );
        $metrics = $assessment['metrics'];

        $output->writeln(sprintf(
            'Reçues: %d · nouvelles: %d · fusionnées: %d · connues: %d · hors profil: %d · erreurs: %d',
            (int) $metrics['received'],
            (int) $metrics['imported'],
            (int) $metrics['merged'],
            (int) $metrics['duplicates'],
            (int) $metrics['profileFiltered'],
            (int) $metrics['failed'],
        ));

        if ($assessment['success']) {
            $output->writeln('<info>'.$assessment['message'].'</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<error>'.$assessment['message'].'</error>');

        return Command::FAILURE;
    }

    private function runningInCi(): bool
    {
        return $this->environmentFlag('CI') || $this->environmentFlag('GITHUB_ACTIONS');
    }

    private function environmentFlag(string $name): bool
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        if ($value === false || $value === null) {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
