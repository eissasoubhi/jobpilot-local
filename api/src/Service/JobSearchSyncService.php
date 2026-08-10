<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ConnectorSyncRun;
use App\Entity\SourceConnector;
use App\JobCatalog\Application\CanonicalJobImportResult;
use App\JobCatalog\Application\CanonicalJobOfferService;
use App\JobCatalog\Application\ProfileFilteredJobOffer;
use App\JobDiscovery\Application\ConnectorDeadLetterService;
use App\JobDiscovery\Application\ConnectorHealthAnalyzer;
use App\JobDiscovery\Application\ConnectorPayloadQualityAnalyzer;
use App\JobDiscovery\Application\ConnectorRegistry;
use App\JobDiscovery\Domain\Connector\JobSourceConnector;
use App\JobDiscovery\Domain\Connector\SearchDiagnosticsConnector;
use App\JobDiscovery\Domain\Connector\VersionedJobSourceConnector;
use Doctrine\ORM\EntityManagerInterface;

final class JobSearchSyncService
{
    public function __construct(
        private ConnectorRegistry $registry,
        private EntityManagerInterface $em,
        private LocalDataService $data,
        private CanonicalJobOfferService $canonicalJobs,
        private ConnectorHealthAnalyzer $healthAnalyzer,
        private ConnectorPayloadQualityAnalyzer $payloadQualityAnalyzer,
        private ConnectorDeadLetterService $deadLetters,
        private string $privateDir,
        private int $intervalSeconds = 21600,
    ) {
        $this->intervalSeconds = max(900, $this->intervalSeconds);
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $states = array_values($this->synchronizeDefinitions());
        $definitions = $this->definitionsByCode();
        usort($states, static fn (SourceConnector $a, SourceConnector $b): int => $a->getName() <=> $b->getName());
        $connectors = array_map(
            fn (SourceConnector $connector): array => $this->connectorStateArray(
                $connector,
                $definitions[$connector->getCode()] ?? null,
            ),
            $states,
        );

        $lastSyncedAt = null;
        foreach ($states as $state) {
            $candidate = $state->getLastSyncedAt();
            if ($candidate !== null && ($lastSyncedAt === null || $candidate > $lastSyncedAt)) {
                $lastSyncedAt = $candidate;
            }
        }
        $nextSyncAt = $lastSyncedAt?->modify(sprintf('+%d seconds', $this->intervalSeconds));
        $aggregate = $this->aggregateLastResults($connectors);
        $alerts = array_values(array_map(
            static fn (array $connector): array => [
                'code' => (string) ($connector['code'] ?? ''),
                'name' => (string) ($connector['name'] ?? ''),
                'status' => (string) ($connector['health']['status'] ?? ''),
                'message' => (string) ($connector['health']['reasons'][0] ?? ''),
            ],
            array_filter(
                $connectors,
                static fn (array $connector): bool => (bool) ($connector['health']['alert'] ?? false),
            ),
        ));

        return [
            'configured' => array_any(
                $states,
                static fn (SourceConnector $connector): bool => $connector->canSynchronize(),
            ),
            'providers' => $connectors,
            'connectors' => $connectors,
            'alerts' => $alerts,
            'lastSyncedAt' => $lastSyncedAt?->format(DATE_ATOM),
            'nextSyncAt' => $nextSyncAt?->format(DATE_ATOM),
            'due' => array_any($states, fn (SourceConnector $connector): bool => $connector->isDue($this->intervalSeconds)),
            'intervalSeconds' => $this->intervalSeconds,
            'lastResult' => $aggregate,
            ...$aggregate,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function connectors(): array
    {
        /** @var list<array<string, mixed>> $connectors */
        $connectors = $this->status()['connectors'];

        return $connectors;
    }

    /** @return list<array<string, mixed>> */
    public function history(int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $runs = $this->em->getRepository(ConnectorSyncRun::class)->findBy([], ['startedAt' => 'DESC'], $limit);

        return array_map(
            static fn (ConnectorSyncRun $run): array => $run->toArray(),
            $runs,
        );
    }

    /** @return array<string, mixed> */
    public function setEnabled(string $code, bool $enabled): array
    {
        $states = $this->synchronizeDefinitions();
        $normalized = strtolower(trim($code));
        if (!isset($states[$normalized])) {
            throw new \InvalidArgumentException(sprintf('Connecteur inconnu : %s.', $code));
        }

        $states[$normalized]->setEnabled($enabled);
        $this->em->flush();

        return $this->connectorStateArray($states[$normalized], $this->registry->get($normalized));
    }

    /** @return array<string, mixed> */
    public function sync(
        bool $force = false,
        ?string $connectorCode = null,
        string $trigger = 'scheduled',
    ): array {
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
            $states = $this->synchronizeDefinitions();
            $connectors = $connectorCode === null
                ? $this->registry->all()
                : [$this->registry->get($connectorCode)];
            $eligible = [];

            foreach ($connectors as $connector) {
                $sourceCode = strtolower($connector->code());
                $state = $states[$sourceCode] ?? null;
                if (!$state instanceof SourceConnector || !$state->canSynchronize()) {
                    continue;
                }
                if ($force || $state->isDue($this->intervalSeconds)) {
                    $eligible[] = $connector;
                }
            }

            if ($eligible === []) {
                return array_merge($this->status(), [
                    'busy' => false,
                    'skipped' => true,
                    'message' => $connectorCode === null
                        ? 'Aucun connecteur autorisé et actif n’est arrivé à échéance.'
                        : 'Ce connecteur est désactivé, incomplet, bloqué par sa politique de collecte ou encore récent.',
                ]);
            }

            $this->deadLetters->pruneStaleTracking();
            $settings = $this->data->settings();
            $profile = $this->data->profile();
            $imported = 0;
            $merged = 0;
            $duplicates = 0;
            $profileFiltered = 0;
            $failed = 0;
            $received = 0;
            $connectorResults = [];
            $errors = [];

            foreach ($eligible as $connector) {
                $sourceCode = strtolower($connector->code());
                $state = $states[$sourceCode];
                $state->markRunning();
                $run = new ConnectorSyncRun($state, $trigger);
                $this->em->persist($run);
                $this->em->flush();

                $connectorImported = 0;
                $connectorMerged = 0;
                $connectorDuplicates = 0;
                $connectorProfileFiltered = 0;
                $connectorFailed = 0;
                $connectorReceived = 0;
                $connectorError = null;
                $connectorErrors = [];
                $fieldQuality = $this->payloadQualityAnalyzer->analyze([]);

                try {
                    $offers = $connector->search($settings->getTargetJobs(), $settings->getSkills());
                    $this->deadLetters->resolveConnectorSearch($sourceCode);
                    $connectorReceived = count($offers);
                    $fieldQuality = $this->payloadQualityAnalyzer->analyze($offers);
                    $received += $connectorReceived;

                    foreach ($offers as $payload) {
                        $externalId = trim((string) ($payload['externalId'] ?? ''));
                        if ($externalId === '') {
                            ++$failed;
                            ++$connectorFailed;
                            $this->deadLetters->recordMissingExternalId($sourceCode, $payload);
                            $connectorErrors[] = 'Offre ignorée car son identifiant externe est vide.';
                            continue;
                        }

                        try {
                            $result = $this->canonicalJobs->import(
                                $payload,
                                $sourceCode,
                                $connector->name(),
                                $connector->mode()->value,
                                $settings,
                                $profile,
                                true,
                            );
                            $this->deadLetters->resolvePayload($sourceCode, $payload);

                            if ($result->outcome() === CanonicalJobImportResult::IMPORTED) {
                                ++$imported;
                                ++$connectorImported;
                            } elseif ($result->outcome() === CanonicalJobImportResult::MERGED) {
                                ++$merged;
                                ++$connectorMerged;
                            } else {
                                ++$duplicates;
                                ++$connectorDuplicates;
                            }
                        } catch (ProfileFilteredJobOffer) {
                            $this->deadLetters->resolvePayload($sourceCode, $payload);
                            ++$profileFiltered;
                            ++$connectorProfileFiltered;
                        } catch (\Throwable $exception) {
                            ++$failed;
                            ++$connectorFailed;
                            $this->deadLetters->recordPayloadFailure($sourceCode, $payload, $exception);
                            if (count($connectorErrors) < 5) {
                                $connectorErrors[] = $exception->getMessage();
                            }
                        }
                    }
                } catch (\Throwable $exception) {
                    ++$failed;
                    ++$connectorFailed;
                    $this->deadLetters->recordConnectorFailure($sourceCode, $exception);
                    $connectorError = $exception->getMessage();
                    $connectorErrors[] = $connectorError;
                    if (count($errors) < 5) {
                        $errors[] = sprintf('%s : %s', $connector->name(), $connectorError);
                    }
                }

                $normalized = $connectorImported + $connectorMerged + $connectorDuplicates + $connectorProfileFiltered;
                $normalizationRate = $connectorReceived > 0
                    ? round($normalized * 100 / $connectorReceived, 1)
                    : null;
                $parserVersion = $connector instanceof VersionedJobSourceConnector
                    ? $connector->parserVersion()
                    : null;
                $searchDiagnostics = $connector instanceof SearchDiagnosticsConnector
                    ? $connector->searchDiagnostics()
                    : null;

                $state->complete(
                    $connectorReceived,
                    $connectorImported,
                    $connectorMerged,
                    $connectorDuplicates,
                    $connectorFailed,
                    $connectorError,
                );
                $run->complete(
                    $connectorReceived,
                    $connectorImported,
                    $connectorMerged,
                    $connectorDuplicates,
                    $connectorFailed,
                    $connectorError,
                    [
                        'errors' => array_slice($connectorErrors, 0, 5),
                        'parserVersion' => $parserVersion,
                        'normalizationRate' => $normalizationRate,
                        'zeroResults' => $connectorReceived === 0,
                        'fieldQuality' => $fieldQuality,
                        'searchDiagnostics' => $searchDiagnostics,
                        'profileFiltered' => $connectorProfileFiltered,
                    ],
                );
                $this->em->flush();

                $connectorResults[] = [
                    'code' => $sourceCode,
                    'name' => $connector->name(),
                    'mode' => $connector->mode()->value,
                    'parserVersion' => $parserVersion,
                    'normalizationRate' => $normalizationRate,
                    'fieldQuality' => $fieldQuality,
                    'searchDiagnostics' => $searchDiagnostics,
                    'received' => $connectorReceived,
                    'imported' => $connectorImported,
                    'merged' => $connectorMerged,
                    'duplicates' => $connectorDuplicates,
                    'profileFiltered' => $connectorProfileFiltered,
                    'failed' => $connectorFailed,
                    'error' => $connectorError,
                ];
            }

            return array_merge($this->status(), [
                'received' => $received,
                'imported' => $imported,
                'merged' => $merged,
                'duplicates' => $duplicates,
                'profileFiltered' => $profileFiltered,
                'failed' => $failed,
                'providers' => $connectorResults,
                'connectorResults' => $connectorResults,
                'errors' => $errors,
                'busy' => false,
                'skipped' => false,
                'message' => sprintf(
                    '%d nouvelle(s) offre(s), %d nouvelle(s) source(s) fusionnée(s), %d offre(s) hors profil non enregistrée(s).',
                    $imported,
                    $merged,
                    $profileFiltered,
                ),
            ]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<string, SourceConnector> */
    private function synchronizeDefinitions(): array
    {
        $repository = $this->em->getRepository(SourceConnector::class);
        $states = [];

        foreach ($this->registry->all() as $connector) {
            $code = strtolower($connector->code());
            $state = $repository->findOneBy(['code' => $code]);
            if (!$state instanceof SourceConnector) {
                $state = new SourceConnector($connector);
                $this->em->persist($state);
            } else {
                $state->refreshDefinition($connector);
            }
            $states[$code] = $state;
        }

        $this->em->flush();

        return $states;
    }

    /** @return array<string, JobSourceConnector> */
    private function definitionsByCode(): array
    {
        $definitions = [];
        foreach ($this->registry->all() as $connector) {
            $definitions[strtolower($connector->code())] = $connector;
        }

        return $definitions;
    }

    /** @return array<string, mixed> */
    private function connectorStateArray(SourceConnector $state, ?JobSourceConnector $definition): array
    {
        $runs = $this->em->getRepository(ConnectorSyncRun::class)->findBy(
            ['connector' => $state],
            ['startedAt' => 'DESC'],
            6,
        );
        $history = array_map(
            static fn (ConnectorSyncRun $run): array => $run->toArray(),
            $runs,
        );
        $latestDetails = is_array($history[0]['details'] ?? null) ? $history[0]['details'] : [];
        $fieldQuality = is_array($latestDetails['fieldQuality'] ?? null)
            ? $latestDetails['fieldQuality']
            : $this->payloadQualityAnalyzer->analyze([]);
        $searchDiagnostics = is_array($latestDetails['searchDiagnostics'] ?? null)
            ? $latestDetails['searchDiagnostics']
            : null;

        return [
            ...$state->toArray($this->intervalSeconds),
            'parserVersion' => $definition instanceof VersionedJobSourceConnector
                ? $definition->parserVersion()
                : null,
            'health' => $this->healthAnalyzer->analyze($history),
            'fieldQuality' => $fieldQuality,
            'searchDiagnostics' => $searchDiagnostics,
            'profileFiltered' => max(0, (int) ($latestDetails['profileFiltered'] ?? 0)),
            'deadLetterOpen' => $this->deadLetters->openCount($state->getCode()),
        ];
    }

    /**
     * @param list<array<string, mixed>> $connectors
     * @return array{received: int, imported: int, merged: int, duplicates: int, failed: int}
     */
    private function aggregateLastResults(array $connectors): array
    {
        $result = ['received' => 0, 'imported' => 0, 'merged' => 0, 'duplicates' => 0, 'failed' => 0];
        foreach ($connectors as $connector) {
            $lastResult = is_array($connector['lastResult'] ?? null) ? $connector['lastResult'] : [];
            foreach (array_keys($result) as $key) {
                $result[$key] += max(0, (int) ($lastResult[$key] ?? 0));
            }
        }

        return $result;
    }

    private function ensurePrivateDirectory(): void
    {
        if (!is_dir($this->privateDir) && !mkdir($this->privateDir, 0700, true) && !is_dir($this->privateDir)) {
            throw new \RuntimeException('Impossible de créer le dossier privé de JobPilot.');
        }
    }
}
