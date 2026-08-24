<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\JobSearchSyncQueue;
use PHPUnit\Framework\TestCase;

final class JobSearchSyncQueueTest extends TestCase
{
    private string $privateDir;

    protected function setUp(): void
    {
        $this->privateDir = sys_get_temp_dir().'/jobpilot-sync-queue-'.bin2hex(random_bytes(6));
        mkdir($this->privateDir, 0770, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->privateDir);
    }

    public function testCurrentReturnsThePublicSnapshotSoAFrontendCanResumePolling(): void
    {
        $queue = new JobSearchSyncQueue($this->privateDir);
        $created = $queue->enqueue(true, null, 'manual');

        $current = $queue->current();

        self::assertNotNull($current);
        self::assertSame($created['id'], $current['id']);
        self::assertSame('queued', $current['status']);
        self::assertArrayNotHasKey('force', $current);
        self::assertArrayNotHasKey('connectorCode', $current);
        self::assertArrayNotHasKey('trigger', $current);
        self::assertNull($current['targetConnectorCodes']);
    }

    public function testSelectedConnectorScopeSurvivesRefreshAndDefinesProgressTotal(): void
    {
        $queue = new JobSearchSyncQueue($this->privateDir);
        $created = $queue->enqueue(true, 'adzuna,france-travail', 'manual-selection', [
            'Adzuna',
            'france-travail',
            'adzuna',
        ]);

        $current = $queue->current();

        self::assertNotNull($current);
        self::assertSame($created['id'], $current['id']);
        self::assertSame(['adzuna', 'france-travail'], $current['targetConnectorCodes']);
        self::assertSame(0, $current['progress']['completed']);
        self::assertSame(2, $current['progress']['total']);
        self::assertArrayNotHasKey('connectorCode', $current);
    }

    public function testRunningProgressTracksOnlySelectedConnectors(): void
    {
        $queue = new JobSearchSyncQueue($this->privateDir);
        $created = $queue->enqueue(true, 'adzuna,france-travail', 'manual-selection', ['adzuna', 'france-travail']);
        self::assertNotNull($queue->claim());

        $queue->updateProgress((string) $created['id'], 1, 2, 'france-travail');
        $current = $queue->current();

        self::assertNotNull($current);
        self::assertSame('running', $current['status']);
        self::assertSame(['adzuna', 'france-travail'], $current['targetConnectorCodes']);
        self::assertSame([
            'completed' => 1,
            'total' => 2,
            'currentConnector' => 'france-travail',
        ], $current['progress']);

        $queue->complete((string) $created['id'], ['failed' => 0, 'errors' => []]);
        $completed = $queue->current();
        self::assertNotNull($completed);
        self::assertSame(2, $completed['progress']['completed']);
        self::assertSame(2, $completed['progress']['total']);
    }

    public function testWorkerHeartbeatDistinguishesMissingActiveAndStaleRuntime(): void
    {
        $queue = new JobSearchSyncQueue($this->privateDir);

        self::assertSame('missing', $queue->workerStatus()['status']);

        $queue->touchWorkerHeartbeat();
        $active = $queue->workerStatus();
        self::assertSame('active', $active['status']);
        self::assertNotNull($active['updatedAt']);

        $path = $this->privateDir.'/job-search-async/worker-heartbeat.json';
        $heartbeat = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $heartbeat['updatedAt'] = (new \DateTimeImmutable('-11 seconds'))->format(DATE_ATOM);
        file_put_contents($path, json_encode($heartbeat, JSON_THROW_ON_ERROR));

        self::assertSame('stale', $queue->workerStatus()['status']);
    }

    public function testQueuedRunFailsFastWhenNoWorkerClaimsIt(): void
    {
        $queue = new JobSearchSyncQueue($this->privateDir);
        $created = $queue->enqueue(true, null, 'manual');
        $path = $this->privateDir.'/job-search-async/current.json';
        $state = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $state['updatedAt'] = (new \DateTimeImmutable('-31 seconds'))->format(DATE_ATOM);
        file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));

        $snapshot = $queue->get((string) $created['id']);

        self::assertNotNull($snapshot);
        self::assertSame('failed', $snapshot['status']);
        self::assertSame('sync_worker_not_started', $snapshot['error']['code']);
        self::assertStringContainsString('Redémarre JobPilot', $snapshot['error']['message']);
    }

    public function testRunningRunKeepsTheLongerStaleWindow(): void
    {
        $queue = new JobSearchSyncQueue($this->privateDir);
        $created = $queue->enqueue(true, null, 'manual');
        $claimed = $queue->claim();
        self::assertNotNull($claimed);

        $path = $this->privateDir.'/job-search-async/current.json';
        $state = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $state['updatedAt'] = (new \DateTimeImmutable('-2 minutes'))->format(DATE_ATOM);
        file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR));

        $snapshot = $queue->get((string) $created['id']);

        self::assertNotNull($snapshot);
        self::assertSame('running', $snapshot['status']);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory.'/'.$item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
