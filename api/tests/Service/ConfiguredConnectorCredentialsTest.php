<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Integration\ConfiguredAdzunaJobProvider;
use App\Service\Integration\ConfiguredFranceTravailJobProvider;
use App\Service\Integration\ConfiguredSmartRecruitersJobProvider;
use App\Service\Integration\ExternalIntegrationConfigurationStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class ConfiguredConnectorCredentialsTest extends TestCase
{
    private string $directory;
    private ExternalIntegrationConfigurationStore $store;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-runtime-credentials-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->store = new ExternalIntegrationConfigurationStore(
            $this->directory,
            base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)),
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testAdzunaConfigurationUpdatesWithoutRecreatingWrapper(): void
    {
        $connector = new ConfiguredAdzunaJobProvider(new MockHttpClient(), $this->store);
        self::assertFalse($connector->isConfigured());

        $this->store->save('adzuna', [
            'values' => ['appId' => 'app-id'],
            'secrets' => ['appKey' => 'app-key'],
        ]);

        self::assertTrue($connector->isConfigured());
    }

    public function testFranceTravailConfigurationUpdatesWithoutRecreatingWrapper(): void
    {
        $connector = new ConfiguredFranceTravailJobProvider(new MockHttpClient(), $this->store);
        self::assertFalse($connector->isConfigured());

        $this->store->save('france-travail', [
            'values' => ['clientId' => 'client-id'],
            'secrets' => ['clientSecret' => 'client-secret'],
        ]);

        self::assertTrue($connector->isConfigured());
    }

    public function testSmartRecruitersConfigurationUpdatesWithoutRecreatingWrapper(): void
    {
        $connector = new ConfiguredSmartRecruitersJobProvider(new MockHttpClient(), $this->store);
        self::assertFalse($connector->isConfigured());

        $this->store->save('smartrecruiters', [
            'values' => ['companyIdentifiers' => 'company-one'],
            'secrets' => ['apiToken' => 'token'],
        ]);

        self::assertTrue($connector->isConfigured());
    }
}
