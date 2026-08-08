<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Ai\AiQuotaManager;
use PHPUnit\Framework\TestCase;

final class AiQuotaManagerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-ai-quota-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testBlocksBeforeSafeRequestsPerMinuteLimitIsExceeded(): void
    {
        $manager = new AiQuotaManager($this->directory);
        $limits = ['rpm' => 2, 'tpm' => 10000, 'rpd' => 100, 'safetyPercent' => 100];

        self::assertNotNull($manager->reserve('gemini', 'model-a', 100, $limits));
        self::assertNotNull($manager->reserve('gemini', 'model-a', 100, $limits));
        self::assertNull($manager->reserve('gemini', 'model-a', 100, $limits));

        $status = $manager->status('gemini', 'model-a', $limits);
        self::assertSame(2, $status['rpmUsed']);
        self::assertSame(2, $status['rpmLimit']);
    }

    public function testSafetyPercentageReducesProviderLimits(): void
    {
        $manager = new AiQuotaManager($this->directory);
        $limits = ['rpm' => 15, 'tpm' => 250000, 'rpd' => 500, 'safetyPercent' => 80];

        $status = $manager->status('gemini', 'gemini-3.5-flash-lite', $limits);

        self::assertSame(12, $status['rpmLimit']);
        self::assertSame(200000, $status['tpmLimit']);
        self::assertSame(400, $status['rpdLimit']);
        self::assertSame('America/Los_Angeles', $status['resetTimeZone']);
    }

    public function testUsageIsIsolatedByProviderAndModel(): void
    {
        $manager = new AiQuotaManager($this->directory);
        $limits = ['rpm' => 1, 'tpm' => 10000, 'rpd' => 100, 'safetyPercent' => 100];

        self::assertNotNull($manager->reserve('gemini', 'model-a', 100, $limits));
        self::assertNull($manager->reserve('gemini', 'model-a', 100, $limits));
        self::assertNotNull($manager->reserve('gemini', 'model-b', 100, $limits));
        self::assertNotNull($manager->reserve('openai', 'model-a', 100, $limits));
    }

    public function testReconcileUsesActualInputTokensReturnedByProvider(): void
    {
        $manager = new AiQuotaManager($this->directory);
        $limits = ['rpm' => 10, 'tpm' => 1000, 'rpd' => 100, 'safetyPercent' => 100];

        $reservation = $manager->reserve('gemini', 'model-a', 300, $limits);
        self::assertNotNull($reservation);
        self::assertSame(300, $manager->status('gemini', 'model-a', $limits)['tpmUsed']);

        $manager->reconcile($reservation, 120);

        self::assertSame(120, $manager->status('gemini', 'model-a', $limits)['tpmUsed']);
    }
}
