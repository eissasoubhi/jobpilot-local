<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Ai\AiUsagePreferencesStore;
use PHPUnit\Framework\TestCase;

final class AiUsagePreferencesStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-ai-usage-preferences-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testSavingOtherPreferencesDoesNotResetUnchangedCreditBaseline(): void
    {
        file_put_contents($this->directory.'/ai-usage-preferences.json', json_encode([
            'billingTier' => 'paid',
            'prepaidCreditUsd' => 10.0,
            'prepaidCreditSetAt' => 1234,
        ], JSON_THROW_ON_ERROR));
        $store = new AiUsagePreferencesStore($this->directory);

        $sameCredit = $store->save([
            'billingTier' => 'free',
            'prepaidCreditUsd' => 10.0,
            'usdToEurRate' => 0.85,
        ]);
        self::assertSame(1234, $sameCredit['prepaidCreditSetAt']);

        $changedCredit = $store->save(['prepaidCreditUsd' => 12.0]);
        self::assertNotSame(1234, $changedCredit['prepaidCreditSetAt']);
    }
}
