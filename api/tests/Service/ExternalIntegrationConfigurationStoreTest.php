<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Integration\ExternalIntegrationConfigurationStore;
use PHPUnit\Framework\TestCase;

final class ExternalIntegrationConfigurationStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-integrations-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testSecretIsEncryptedAndNeverExposedByPublicConfiguration(): void
    {
        $store = $this->store();
        $store->save('openai', [
            'values' => ['model' => 'test-model'],
            'secrets' => ['apiKey' => 'super-secret-openai-key'],
        ]);

        $configuration = $store->publicConfiguration('openai');
        $persisted = (string) file_get_contents($this->directory.'/external-integrations.enc');

        self::assertStringNotContainsString('super-secret-openai-key', $persisted);
        self::assertSame('test-model', $configuration['fields']['model']['value']);
        self::assertTrue($configuration['fields']['apiKey']['configured']);
        self::assertSame('interface', $configuration['fields']['apiKey']['source']);
        self::assertNull($configuration['fields']['apiKey']['value']);
    }

    public function testInterfaceValuesOverrideEnvironmentAndClearingSecretFallsBackToEnvironment(): void
    {
        $store = $this->store([
            'adzuna' => ['appId' => 'env-app-id', 'appKey' => 'env-app-key'],
        ]);

        $store->save('adzuna', [
            'values' => ['appId' => 'ui-app-id'],
            'secrets' => ['appKey' => 'ui-app-key'],
        ]);

        self::assertSame([
            'appId' => 'ui-app-id',
            'appKey' => 'ui-app-key',
        ], $store->effective('adzuna'));

        $configuration = $store->save('adzuna', ['clearSecrets' => ['appKey']]);

        self::assertSame('ui-app-id', $store->effective('adzuna')['appId']);
        self::assertSame('env-app-key', $store->effective('adzuna')['appKey']);
        self::assertSame('environment', $configuration['fields']['appKey']['source']);
    }

    public function testBlankSecretPreservesStoredSecret(): void
    {
        $store = $this->store();
        $store->save('france-travail', [
            'values' => ['clientId' => 'client-id'],
            'secrets' => ['clientSecret' => 'first-secret'],
        ]);

        $store->save('france-travail', [
            'values' => ['clientId' => 'client-id-2'],
            'secrets' => ['clientSecret' => ''],
        ]);

        self::assertSame('client-id-2', $store->effective('france-travail')['clientId']);
        self::assertSame('first-secret', $store->effective('france-travail')['clientSecret']);
    }

    /** @param array<string, array<string, string>> $environment */
    private function store(array $environment = []): ExternalIntegrationConfigurationStore
    {
        return new ExternalIntegrationConfigurationStore(
            $this->directory,
            base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)),
            $environment,
        );
    }
}
