<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\JobSearchSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:jobs:sync', description: 'Recherche et importe automatiquement de nouvelles offres.')]
final class SyncJobsCommand extends Command
{
    public function __construct(private JobSearchSyncService $syncService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ignore l’intervalle entre deux synchronisations.')
            ->addOption('connector', null, InputOption::VALUE_REQUIRED, 'Limite la synchronisation à un code de connecteur.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $connector = trim((string) $input->getOption('connector'));
            $result = $this->syncService->sync(
                (bool) $input->getOption('force'),
                $connector !== '' ? $connector : null,
                'cli',
            );
            $output->writeln((string) ($result['message'] ?? 'Synchronisation terminée.'));
            $output->writeln(sprintf(
                'Reçues : %d, importées : %d, doublons : %d, échecs : %d',
                (int) ($result['received'] ?? 0),
                (int) ($result['imported'] ?? 0),
                (int) ($result['duplicates'] ?? 0),
                (int) ($result['failed'] ?? 0),
            ));

            foreach ($result['connectorResults'] ?? $result['providers'] ?? [] as $provider) {
                if (!is_array($provider) || !isset($provider['name'])) {
                    continue;
                }
                $output->writeln(sprintf(
                    ' - %s : %d importée(s), %d doublon(s)%s',
                    (string) $provider['name'],
                    (int) ($provider['imported'] ?? 0),
                    (int) ($provider['duplicates'] ?? 0),
                    isset($provider['error']) && $provider['error'] !== null ? ' — '.$provider['error'] : '',
                ));
            }

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');

            return Command::FAILURE;
        }
    }
}
