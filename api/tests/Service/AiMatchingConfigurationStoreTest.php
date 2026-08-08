<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Ai\AiMatchingConfigurationStore;
use PHPUnit\Framework\TestCase;

final class AiMatchingConfigurationStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-ai-config-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testInterfaceOverridesAreEncryptedAndNeverExposedByPublicConfiguration(): void
    {
        $store = $this->store();

        $public = $store->save([
            'enabled' => true,
            'model' => 'gemini-3.5-flash-lite',
            'apiKey' => 'ui-secret-key',
        ]);

        self::assertTrue($public['enabled']);
        self::assertTrue($public['apiKeyConfigured']);
        self::assertSame('interface', $public['apiKeySource']);
        self::assertArrayNotHasKey('apiKey', $public);
        self::assertSame('ui-secret-key', $store->effective()['apiKey']);

        $encrypted = (string) file_get_contents($this->directory.'/ai-matching-config.enc');
        self::assertStringNotContainsString('ui-secret-key', $encrypted);
    }

    public function testClearingInterfaceKeyFallsBackToEnvironmentKey(): void
    {
        $store = $this->store();
        $store->save(['apiKey' => 'ui-secret-key']);

        $public = $store->save(['clearApiKey' => true]);

        self::assertTrue($public['apiKeyConfigured']);
        self::assertSame('environment', $public['apiKeySource']);
        self::assertSame('env-secret-key', $store->effective()['apiKey']);
    }

    public function testBlankKeyPreservesExistingInterfaceSecret(): void
    {
        $store = $this->store();
        $store->save(['apiKey' => 'ui-secret-key']);

        $store->save(['enabled' => true, 'apiKey' => '']);

        self::assertSame('ui-secret-key', $store->effective()['apiKey']);
    }

    public function testQuotaLimitsCanBeOverriddenFromTheInterface(): void
    {
        $store = $this->store();

        $public = $store->save([
            'quotaRpm' => 15,
            'quotaTpm' => 250000,
            'quotaRpd' => 500,
            'quotaSafetyPercent' => 80,
        ]);

        self::assertSame([
            'rpm' => 15,
            'tpm' => 250000,
            'rpd' => 500,
            'safetyPercent' => 80,
        ], $public['quota']);
        self::assertSame($public['quota'], $store->effective()['quota']);
    }

    public function testInvalidQuotaIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->store()->save(['quotaSafetyPercent' => 101]);
    }

    private function store(): AiMatchingConfigurationStore
    {
        return new AiMatchingConfigurationStore(
            $this->directory,
            'test-encryption-key',
            false,
            'env-secret-key',
            'gemini-env-model',
            20,
            300000,
            600,
            90,
        );
    }
}
