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
        $this->queue->touchWorkerHeartbeat();

        $job = $this->queue->claim();
        if ($job === null) {
            return Command::SUCCESS;
        }

        $id = (string) ($job['id'] ?? '');
        if ($id === '') {
            return Command::FAILURE;
        }

        try {
            $connectorCode = isset($job['connectorCode']) && is_string($job['connectorCode'])
                ? trim($job['connectorCode'])
                : '';
            $connectorCodes = $connectorCode === ''
                ? []
                : array_values(array_filter(array_map('trim', explode(',', $connectorCode))));

            $result = count($connectorCodes) > 1
                ? $this->syncSelectedConnectors(
                    (bool) ($job['force'] ?? false),
                    $connectorCodes,
                    (string) ($job['trigger'] ?? 'async'),
                )
                : $this->syncService->sync(
                    (bool) ($job['force'] ?? false),
                    $connectorCodes[0] ?? null,
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

    /**
     * @param list<string> $connectorCodes
     * @return array<string, mixed>
     */
    private function syncSelectedConnectors(bool $force, array $connectorCodes, string $trigger): array
    {
        $aggregate = [
            'received' => 0,
            'imported' => 0,
            'merged' => 0,
            'duplicates' => 0,
            'profileFiltered' => 0,
            'failed' => 0,
            'providers' => [],
            'connectorResults' => [],
            'errors' => [],
            'busy' => false,
            'skipped' => false,
        ];
        $lastResult = null;

        foreach ($connectorCodes as $connectorCode) {
            $result = $this->syncService->sync($force, $connectorCode, $trigger);
            if ((bool) ($result['busy'] ?? false)) {
                return $result;
            }

            $lastResult = $result;
            foreach (['received', 'imported', 'merged', 'duplicates', 'profileFiltered', 'failed'] as $counter) {
                $aggregate[$counter] += max(0, (int) ($result[$counter] ?? 0));
            }
            foreach (($result['connectorResults'] ?? $result['providers'] ?? []) as $provider) {
                if (is_array($provider)) {
                    $aggregate['providers'][] = $provider;
                    $aggregate['connectorResults'][] = $provider;
                }
            }
            foreach (($result['errors'] ?? []) as $error) {
                if (is_string($error) && trim($error) !== '' && count($aggregate['errors']) < 5) {
                    $aggregate['errors'][] = $error;
                }
            }
        }

        if (is_array($lastResult)) {
            foreach (['configured', 'lastSyncedAt', 'nextSyncAt', 'due', 'intervalSeconds'] as $key) {
                if (array_key_exists($key, $lastResult)) {
                    $aggregate[$key] = $lastResult[$key];
                }
            }
        }

        $aggregate['message'] = sprintf(
            '%d connecteur(s) ciblé(s) synchronisé(s) : %d nouvelle(s) offre(s), %d source(s) fusionnée(s).',
            count($connectorCodes),
            $aggregate['imported'],
            $aggregate['merged'],
        );

        return $aggregate;
    }
}
