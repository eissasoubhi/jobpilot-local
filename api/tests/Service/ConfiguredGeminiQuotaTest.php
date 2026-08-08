<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\Ai\AiMatchingCache;
use App\Service\Ai\AiMatchingConfigurationStore;
use App\Service\Ai\AiQuotaManager;
use App\Service\Ai\ConfiguredGeminiJobMatchAnalyzer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConfiguredGeminiQuotaTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-configured-gemini-quota-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testQuotaGuardSkipsGeminiWithoutSendingAnHttpRequest(): void
    {
        $configuration = new AiMatchingConfigurationStore(
            $this->directory,
            'test-encryption-key',
            true,
            'test-key',
            'gemini-3.5-flash-lite',
            1,
            250000,
            500,
            100,
        );
        $quotaManager = new AiQuotaManager($this->directory);
        $limits = $configuration->effective()['quota'];
        self::assertNotNull($quotaManager->reserve('gemini', 'gemini-3.5-flash-lite', 100, $limits));

        $client = new MockHttpClient(static function (): MockResponse {
            self::fail('Gemini must not be called once the local safe quota is reached.');
        });
        $analyzer = new ConfiguredGeminiJobMatchAnalyzer(
            $client,
            new NullLogger(),
            $configuration,
            $quotaManager,
            new AiMatchingCache($this->directory),
        );

        self::assertNull($analyzer->analyze($this->job(), $this->settings()));
        self::assertSame(0, $client->getRequestsCount());
    }

    private function job(): JobOffer
    {
        return (new JobOffer())->fill([
            'title' => 'Senior PHP Symfony Developer',
            'description' => 'Symfony 8, PHP 8, APIs and PostgreSQL.',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybrid',
        ]);
    }

    private function settings(): UserSettings
    {
        return (new UserSettings())->fill([
            'targetJobs' => ['Senior PHP Symfony Developer'],
            'skills' => ['PHP', 'Symfony', 'React'],
            'exclusions' => ['Stage'],
        ]);
    }
}
