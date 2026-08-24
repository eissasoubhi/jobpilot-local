<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class JobSearchSyncQueue
{
    private const QUEUED_STALE_AFTER_SECONDS = 30;
    private const RUNNING_STALE_AFTER_SECONDS = 1800;
    private const WORKER_HEARTBEAT_STALE_AFTER_SECONDS = 10;

    public function __construct(#[Autowire('%private_dir%')] private string $privateDir)
    {
    }

    /**
     * @param list<string>|null $targetConnectorCodes
     * @return array<string, mixed>
     */
    public function enqueue(bool $force, ?string $connectorCode, string $trigger, ?array $targetConnectorCodes = null): array
    {
        return $this->withLock(function () use ($force, $connectorCode, $trigger, $targetConnectorCodes): array {
            $current = $this->readState();
            if (is_array($current) && $this->isActive($current) && !$this->isStale($current)) {
                $current['deduplicated'] = true;

                return $current;
            }

            $targets = $this->normalizeConnectorCodes($targetConnectorCodes);
            $now = $this->now();
            $state = [
                'id' => bin2hex(random_bytes(16)),
                'status' => 'queued',
                'force' => $force,
                'connectorCode' => $connectorCode,
                'trigger' => $trigger,
                'targetConnectorCodes' => $targets,
                'queuedAt' => $now,
                'startedAt' => null,
                'finishedAt' => null,
                'updatedAt' => $now,
                'progress' => [
                    'completed' => 0,
                    'total' => $targets === null ? 1 : count($targets),
                    'currentConnector' => null,
                ],
                'result' => null,
                'error' => null,
                'deduplicated' => false,
            ];
            $this->writeState($state);

            return $state;
        });
    }

    /** @return array<string, mixed>|null */
    public function claim(): ?array
    {
        return $this->withLock(function (): ?array {
            $state = $this->readState();
            if (!is_array($state) || ($state['status'] ?? null) !== 'queued') {
                return null;
            }

            if ($this->isStale($state)) {
                $this->markStale($state);
                $this->writeState($state);

                return null;
            }

            $now = $this->now();
            $state['status'] = 'running';
            $state['startedAt'] = $now;
            $state['updatedAt'] = $now;
            $state['deduplicated'] = false;
            $this->writeState($state);

            return $state;
        });
    }

    public function updateProgress(string $id, int $completed, int $total, ?string $currentConnector): void
    {
        $this->withLock(function () use ($id, $completed, $total, $currentConnector): void {
            $state = $this->readMatchingState($id);
            if ($state === null || ($state['status'] ?? null) !== 'running') {
                return;
            }

            $safeTotal = max(1, $total);
            $state['progress'] = [
                'completed' => min(max(0, $completed), $safeTotal),
                'total' => $safeTotal,
                'currentConnector' => $currentConnector,
            ];
            $state['updatedAt'] = $this->now();
            $this->writeState($state);
        });
    }

    public function touchWorkerHeartbeat(): void
    {
        $this->ensureDirectory();
        $payload = json_encode([
            'updatedAt' => $this->now(),
            'pid' => getmypid() ?: null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $temporary = $this->workerHeartbeatPath().'.tmp.'.bin2hex(random_bytes(4));
        if (file_put_contents($temporary, $payload, LOCK_EX) === false || !rename($temporary, $this->workerHeartbeatPath())) {
            @unlink($temporary);
            throw new \RuntimeException('Impossible d’enregistrer le heartbeat du worker de synchronisation.');
        }
    }

    /** @return array{status: 'active'|'stale'|'missing', updatedAt: string|null} */
    public function workerStatus(): array
    {
        $path = $this->workerHeartbeatPath();
        if (!is_file($path)) {
            return ['status' => 'missing', 'updatedAt' => null];
        }

        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            return ['status' => 'missing', 'updatedAt' => null];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            $updatedAt = is_array($decoded) ? (string) ($decoded['updatedAt'] ?? '') : '';
            $timestamp = $updatedAt !== '' ? new \DateTimeImmutable($updatedAt) : null;
        } catch (\Throwable) {
            return ['status' => 'stale', 'updatedAt' => null];
        }

        if ($timestamp === null) {
            return ['status' => 'stale', 'updatedAt' => null];
        }

        return [
            'status' => $timestamp->getTimestamp() >= time() - self::WORKER_HEARTBEAT_STALE_AFTER_SECONDS
                ? 'active'
                : 'stale',
            'updatedAt' => $updatedAt,
        ];
    }

    /** @param array<string, mixed> $result */
    public function complete(string $id, array $result): void
    {
        $this->withLock(function () use ($id, $result): void {
            $state = $this->readMatchingState($id);
            if ($state === null) {
                return;
            }

            $errors = array_values(array_filter(
                $result['errors'] ?? [],
                static fn (mixed $error): bool => is_string($error) && trim($error) !== '',
            ));
            $failed = max(0, (int) ($result['failed'] ?? 0));
            $state['status'] = $failed > 0 || $errors !== [] ? 'partial' : 'success';
            $state['finishedAt'] = $this->now();
            $state['updatedAt'] = $state['finishedAt'];
            $total = max(1, (int) ($state['progress']['total'] ?? 1));
            $state['progress'] = [
                'completed' => $total,
                'total' => $total,
                'currentConnector' => null,
            ];
            $state['result'] = [
                'message' => (string) ($result['message'] ?? 'Synchronisation terminée.'),
                'received' => max(0, (int) ($result['received'] ?? 0)),
                'imported' => max(0, (int) ($result['imported'] ?? 0)),
                'merged' => max(0, (int) ($result['merged'] ?? 0)),
                'duplicates' => max(0, (int) ($result['duplicates'] ?? 0)),
                'profileFiltered' => max(0, (int) ($result['profileFiltered'] ?? 0)),
                'failed' => $failed,
                'errors' => array_slice($errors, 0, 5),
                'providers' => $this->safeProviders($result['connectorResults'] ?? $result['providers'] ?? []),
                'lastSyncedAt' => isset($result['lastSyncedAt']) && is_string($result['lastSyncedAt'])
                    ? $result['lastSyncedAt']
                    : null,
                'nextSyncAt' => isset($result['nextSyncAt']) && is_string($result['nextSyncAt'])
                    ? $result['nextSyncAt']
                    : null,
            ];
            $state['error'] = null;
            $this->writeState($state);
        });
    }

    public function fail(string $id, \Throwable $exception): void
    {
        $this->withLock(function () use ($id, $exception): void {
            $state = $this->readMatchingState($id);
            if ($state === null) {
                return;
            }

            $state['status'] = 'failed';
            $state['finishedAt'] = $this->now();
            $state['updatedAt'] = $state['finishedAt'];
            $total = max(1, (int) ($state['progress']['total'] ?? 1));
            $state['progress'] = [
                'completed' => min(max(0, (int) ($state['progress']['completed'] ?? 0)), $total),
                'total' => $total,
                'currentConnector' => null,
            ];
            $state['result'] = null;
            $state['error'] = [
                'code' => 'sync_failed',
                'message' => 'La recherche d’offres a échoué. Les données déjà enregistrées restent disponibles.',
                'type' => $exception::class,
            ];
            $this->writeState($state);
        });
    }

    public function requeue(string $id): void
    {
        $this->withLock(function () use ($id): void {
            $state = $this->readMatchingState($id);
            if ($state === null) {
                return;
            }

            $state['status'] = 'queued';
            $state['startedAt'] = null;
            $state['updatedAt'] = $this->now();
            $this->writeState($state);
        });
    }

    /** @return array<string, mixed>|null */
    public function get(string $id): ?array
    {
        return $this->withLock(function () use ($id): ?array {
            $state = $this->readMatchingState($id);
            if ($state === null) {
                return null;
            }

            return $this->publicState($state);
        });
    }

    /** @return array<string, mixed>|null */
    public function current(): ?array
    {
        return $this->withLock(function (): ?array {
            $state = $this->readState();
            if (!is_array($state)) {
                return null;
            }

            return $this->publicState($state);
        });
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function publicState(array $state): array
    {
        if ($this->isActive($state) && $this->isStale($state)) {
            $this->markStale($state);
            $this->writeState($state);
        }

        unset($state['force'], $state['connectorCode'], $state['trigger']);

        return $state;
    }

    /** @param array<string, mixed> $state */
    private function markStale(array &$state): void
    {
        $wasQueued = ($state['status'] ?? null) === 'queued';
        $state['status'] = 'failed';
        $state['finishedAt'] = $this->now();
        $state['updatedAt'] = $state['finishedAt'];
        $state['error'] = [
            'code' => $wasQueued ? 'sync_worker_not_started' : 'sync_worker_stale',
            'message' => $wasQueued
                ? 'Le worker de recherche n’a pas démarré. Redémarre JobPilot pour recréer le scheduler avec la version courante, puis relance la recherche.'
                : 'Le worker de recherche ne répond plus. Une nouvelle recherche peut être lancée.',
        ];
    }

    private function withLock(callable $callback): mixed
    {
        $this->ensureDirectory();
        $lock = fopen($this->queueDirectory().'/queue.lock', 'c+');
        if ($lock === false) {
            throw new \RuntimeException('Impossible d’ouvrir le verrou de la file de synchronisation.');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Impossible de verrouiller la file de synchronisation.');
            }

            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<string, mixed>|null */
    private function readMatchingState(string $id): ?array
    {
        $state = $this->readState();

        return is_array($state) && hash_equals((string) ($state['id'] ?? ''), $id) ? $state : null;
    }

    /** @return array<string, mixed>|null */
    private function readState(): ?array
    {
        $path = $this->statePath();
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed> $state */
    private function writeState(array $state): void
    {
        $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $temporary = $this->statePath().'.tmp.'.bin2hex(random_bytes(4));
        if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $this->statePath())) {
            @unlink($temporary);
            throw new \RuntimeException('Impossible d’enregistrer l’état de la synchronisation.');
        }
    }

    /** @param array<string, mixed> $state */
    private function isActive(array $state): bool
    {
        return in_array($state['status'] ?? null, ['queued', 'running'], true);
    }

    /** @param array<string, mixed> $state */
    private function isStale(array $state): bool
    {
        $timestamp = (string) ($state['updatedAt'] ?? $state['startedAt'] ?? $state['queuedAt'] ?? '');
        if ($timestamp === '') {
            return true;
        }

        try {
            $updatedAt = new \DateTimeImmutable($timestamp);
        } catch (\Throwable) {
            return true;
        }

        $staleAfter = ($state['status'] ?? null) === 'queued'
            ? self::QUEUED_STALE_AFTER_SECONDS
            : self::RUNNING_STALE_AFTER_SECONDS;

        return $updatedAt->getTimestamp() < time() - $staleAfter;
    }

    /** @return list<array<string, mixed>> */
    private function safeProviders(mixed $providers): array
    {
        if (!is_array($providers)) {
            return [];
        }

        $safe = [];
        foreach ($providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }
            $safe[] = [
                'code' => (string) ($provider['code'] ?? ''),
                'name' => (string) ($provider['name'] ?? ''),
                'received' => max(0, (int) ($provider['received'] ?? 0)),
                'imported' => max(0, (int) ($provider['imported'] ?? 0)),
                'merged' => max(0, (int) ($provider['merged'] ?? 0)),
                'duplicates' => max(0, (int) ($provider['duplicates'] ?? 0)),
                'failed' => max(0, (int) ($provider['failed'] ?? 0)),
                'error' => isset($provider['error']) && is_string($provider['error'])
                    ? $provider['error']
                    : null,
            ];
        }

        return $safe;
    }

    /** @param list<string>|null $codes
     * @return list<string>|null
     */
    private function normalizeConnectorCodes(?array $codes): ?array
    {
        if ($codes === null) {
            return null;
        }

        $normalized = [];
        foreach ($codes as $code) {
            if (!is_string($code)) {
                continue;
            }
            $code = strtolower(trim($code));
            if ($code !== '' && !in_array($code, $normalized, true)) {
                $normalized[] = $code;
            }
        }

        return $normalized;
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->queueDirectory()) && !mkdir($this->queueDirectory(), 0770, true) && !is_dir($this->queueDirectory())) {
            throw new \RuntimeException('Impossible de créer le dossier de la file de synchronisation.');
        }
    }

    private function queueDirectory(): string
    {
        return rtrim($this->privateDir, '/').'/job-search-async';
    }

    private function statePath(): string
    {
        return $this->queueDirectory().'/current.json';
    }

    private function workerHeartbeatPath(): string
    {
        return $this->queueDirectory().'/worker-heartbeat.json';
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format(DATE_ATOM);
    }
}
