<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\JobSearchSyncQueue;
use App\Service\JobSearchSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:jobs:sync-worker', description: 'Traite une recherche d’offres asynchrone hors du serveur HTTP.')]
final class JobSearchSyncWorkerCommand extends Command
{
    public function __construct(
        private JobSearchSyncQueue $queue,
        private JobSearchSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $job = $this->queue->claim();
        if ($job === null) {
            return Command::SUCCESS;
        }

        $id = (string) ($job['id'] ?? '');
        if ($id === '') {
            return Command::FAILURE;
        }

        try {
            $result = $this->syncService->sync(
                (bool) ($job['force'] ?? false),
                isset($job['connectorCode']) && is_string($job['connectorCode']) && trim($job['connectorCode']) !== ''
                    ? trim($job['connectorCode'])
                    : null,
                (string) ($job['trigger'] ?? 'async'),
            );

            if ((bool) ($result['busy'] ?? false)) {
                $this->queue->requeue($id);
                $output->writeln('Synchronisation déjà occupée ; tâche remise en file.');

                return Command::SUCCESS;
            }

            $this->queue->complete($id, $result);
            $output->writeln((string) ($result['message'] ?? 'Synchronisation terminée.'));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $this->queue->fail($id, $exception);
            $output->writeln('<error>La synchronisation asynchrone a échoué.</error>');

            return Command::FAILURE;
        }
    }
}
