<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TestDatabaseIsolationBootstrapTest extends TestCase
{
    public function testBootstrapRefusesApplicationDatabase(): void
    {
        $result = $this->runBootstrap('postgresql://jobpilot:jobpilot@db:5432/jobpilot?serverVersion=17');

        self::assertNotSame(0, $result['exitCode']);
        self::assertStringContainsString('Unsafe PHPUnit database "jobpilot"', $result['stderr']);
    }

    public function testBootstrapAcceptsDatabaseEndingInTest(): void
    {
        $result = $this->runBootstrap('postgresql://jobpilot:jobpilot@db:5432/jobpilot_test?serverVersion=17');

        self::assertSame(0, $result['exitCode'], $result['stderr']);
    }

    /** @return array{exitCode: int, stderr: string} */
    private function runBootstrap(string $databaseUrl): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $environment = [
            'DATABASE_URL' => $databaseUrl,
            'TEST_DATABASE_URL' => '',
        ];

        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__).'/bootstrap.php'],
            $descriptors,
            $pipes,
            dirname(__DIR__, 2),
            $environment,
        );
        self::assertIsResource($process);

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'stderr' => $stderr,
        ];
    }
}
