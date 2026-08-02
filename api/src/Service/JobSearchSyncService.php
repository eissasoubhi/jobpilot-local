<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;

final class JobSearchSyncService
{
    /** @var list<JobProviderInterface> */
    private array $providers;

    /** @param iterable<JobProviderInterface> $providers */
    public function __construct(
        iterable $providers,
        private EntityManagerInterface $em,
        private LocalDataService $data,
        private JobProcessor $processor,
        private string $privateDir,
        private int $intervalSeconds = 21600,
    ) {
        $this->providers = is_array($providers) ? array_values($providers) : iterator_to_array($providers, false);
        $this->intervalSeconds = max(900, $this->intervalSeconds);
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $state = $this->readState();
        $lastSyncedAt = isset($state['lastSyncedAt']) ? (string) $state['lastSyncedAt'] : null;
        $lastTimestamp = $lastSyncedAt !== null ? strtotime($lastSyncedAt) : false;
        $nextTimestamp = $lastTimestamp === false ? null : $lastTimestamp + $this->intervalSeconds;

        return [
            'configured' => $this->configuredProviders() !== [],
            'providers' => array_map(
                static fn (JobProviderInterface $provider): array => [
                    'name' => $provider->name(),
                    'configured' => $provider->isConfigured(),
                ],
                $this->providers,
            ),
            'lastSyncedAt' => $lastSyncedAt,
            'nextSyncAt' => $nextTimestamp === null ? null : date(DATE_ATOM, $nextTimestamp),
            'due' => $nextTimestamp === null || time() >= $nextTimestamp,
            'intervalSeconds' => $this->intervalSeconds,
            'lastResult' => $state['lastResult'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    public function sync(bool $force = false): array
    {
        $this->ensurePrivateDirectory();
        $lock = fopen($this->privateDir.'/job-search-sync.lock', 'c+');
        if ($lock === false) {
            throw new \RuntimeException('Impossible de créer le verrou de synchronisation des offres.');
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return array_merge($this->status(), [
                'busy' => true,
                'skipped' => true,
                'message' => 'Une recherche d’offres est déjà en cours.',
            ]);
        }

        try {
            $status = $this->status();
            if (!$force && !$status['due']) {
                return array_merge($status, [
                    'busy' => false,
                    'skipped' => true,
                    'message' => 'La dernière recherche est encore récente.',
                ]);
            }

            $providers = $this->configuredProviders();
            if ($providers === []) {
                return array_merge($status, [
                    'busy' => false,
                    'skipped' => true,
                    'message' => 'Aucune source d’offres n’est configurée.',
                ]);
            }

            $settings = $this->data->settings();
            $profile = $this->data->profile();
            $imported = 0;
            $duplicates = 0;
            $failed = 0;
            $received = 0;
            $providerResults = [];
            $errors = [];

            foreach ($providers as $provider) {
                $providerImported = 0;
                $providerDuplicates = 0;
                $providerFailed = 0;

                try {
                    $offers = $provider->search($settings->getTargetJobs(), $settings->getSkills());
                    $received += count($offers);

                    foreach ($offers as $payload) {
                        $externalId = trim((string) ($payload['externalId'] ?? ''));
                        if ($externalId === '') {
                            ++$failed;
                            ++$providerFailed;
                            continue;
                        }

                        $existing = $this->em->getRepository(JobOffer::class)->findOneBy([
                            'source' => $provider->name(),
                            'externalId' => $externalId,
                        ]);
                        if ($existing !== null) {
                            ++$duplicates;
                            ++$providerDuplicates;
                            continue;
                        }

                        try {
                            $job = (new JobOffer())->fill($payload);
                            if ($job->getTitle() === '' || $job->getDescription() === '') {
                                throw new \InvalidArgumentException('Offre sans titre ou description.');
                            }
                            $this->processor->process($job, $settings, $profile);
                            ++$imported;
                            ++$providerImported;
                        } catch (\Throwable $exception) {
                            ++$failed;
                            ++$providerFailed;
                            if (count($errors) < 5) {
                                $errors[] = sprintf('%s : %s', $provider->name(), $exception->getMessage());
                            }
                        }
                    }

                    $providerResults[] = [
                        'name' => $provider->name(),
                        'received' => count($offers),
                        'imported' => $providerImported,
                        'duplicates' => $providerDuplicates,
                        'failed' => $providerFailed,
                        'error' => null,
                    ];
                } catch (\Throwable $exception) {
                    ++$failed;
                    $providerResults[] = [
                        'name' => $provider->name(),
                        'received' => 0,
                        'imported' => 0,
                        'duplicates' => 0,
                        'failed' => 1,
                        'error' => $exception->getMessage(),
                    ];
                    if (count($errors) < 5) {
                        $errors[] = sprintf('%s : %s', $provider->name(), $exception->getMessage());
                    }
                }
            }

            $now = new \DateTimeImmutable();
            $result = [
                'received' => $received,
                'imported' => $imported,
                'duplicates' => $duplicates,
                'failed' => $failed,
                'providers' => $providerResults,
                'errors' => $errors,
            ];
            $this->writeState([
                'lastSyncedAt' => $now->format(DATE_ATOM),
                'lastResult' => $result,
            ]);

            return array_merge($this->status(), $result, [
                'busy' => false,
                'skipped' => false,
                'message' => sprintf('%d nouvelle(s) offre(s) importée(s).', $imported),
            ]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return list<JobProviderInterface> */
    private function configuredProviders(): array
    {
        return array_values(array_filter(
            $this->providers,
            static fn (JobProviderInterface $provider): bool => $provider->isConfigured(),
        ));
    }

    /** @return array<string, mixed> */
    private function readState(): array
    {
        $path = $this->privateDir.'/job-search-sync.json';
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $state */
    private function writeState(array $state): void
    {
        $this->ensurePrivateDirectory();
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $temporary = $this->privateDir.'/job-search-sync.json.tmp';
        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Impossible d’enregistrer l’état de synchronisation.');
        }
        if (!rename($temporary, $this->privateDir.'/job-search-sync.json')) {
            @unlink($temporary);
            throw new \RuntimeException('Impossible de finaliser l’état de synchronisation.');
        }
    }

    private function ensurePrivateDirectory(): void
    {
        if (!is_dir($this->privateDir) && !mkdir($this->privateDir, 0700, true) && !is_dir($this->privateDir)) {
            throw new \RuntimeException('Impossible de créer le dossier privé de JobPilot.');
        }
    }
}
