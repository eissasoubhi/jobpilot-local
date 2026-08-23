<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\JobSearchSyncQueue;
use PHPUnit\Framework\TestCase;

final class JobSearchSyncQueueTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-sync-queue-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testItDeduplicatesAnActiveRunAndCompletesIt(): void
    {
        $queue = new JobSearchSyncQueue($this->directory);

        $first = $queue->enqueue(true, null, 'manual');
        $second = $queue->enqueue(true, null, 'manual');

        self::assertSame($first['id'], $second['id']);
        self::assertTrue($second['deduplicated']);

        $claimed = $queue->claim();
        self::assertNotNull($claimed);
        self::assertSame($first['id'], $claimed['id']);
        self::assertSame('running', $claimed['status']);
        self::assertNull($queue->claim());

        $queue->complete((string) $first['id'], [
            'message' => 'Terminé.',
            'received' => 12,
            'imported' => 3,
            'merged' => 2,
            'duplicates' => 7,
            'failed' => 0,
            'errors' => [],
            'connectorResults' => [
                ['code' => 'test', 'name' => 'Test', 'received' => 12, 'imported' => 3, 'failed' => 0],
            ],
        ]);

        $completed = $queue->get((string) $first['id']);
        self::assertNotNull($completed);
        self::assertSame('success', $completed['status']);
        self::assertSame(3, $completed['result']['imported']);
        self::assertArrayNotHasKey('force', $completed);
        self::assertArrayNotHasKey('trigger', $completed);

        $next = $queue->enqueue(false, null, 'page-load');
        self::assertNotSame($first['id'], $next['id']);
    }

    public function testItReportsPartialAndFailedRunsWithoutRawExceptionMessages(): void
    {
        $queue = new JobSearchSyncQueue($this->directory);
        $partial = $queue->enqueue(true, null, 'manual');
        self::assertNotNull($queue->claim());

        $queue->complete((string) $partial['id'], [
            'message' => 'Partiel.',
            'failed' => 1,
            'errors' => ['Adzuna : temporairement indisponible'],
        ]);

        $partialState = $queue->get((string) $partial['id']);
        self::assertSame('partial', $partialState['status']);
        self::assertSame(1, $partialState['result']['failed']);

        $failed = $queue->enqueue(true, null, 'manual');
        self::assertNotNull($queue->claim());
        $queue->fail((string) $failed['id'], new \RuntimeException('<b>secret upstream body</b>'));

        $failedState = $queue->get((string) $failed['id']);
        self::assertSame('failed', $failedState['status']);
        self::assertSame('sync_failed', $failedState['error']['code']);
        self::assertStringNotContainsString('secret upstream body', (string) $failedState['error']['message']);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $directory.'/'.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
