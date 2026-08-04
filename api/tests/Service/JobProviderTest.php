<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\Service\AdzunaJobProvider;
use App\Service\ArbeitnowJobProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class JobProviderTest extends TestCase
{
    public function testArbeitnowFiltersAndNormalizesMatchingFrenchJob(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'data' => [
                [
                    'slug' => 'senior-symfony-paris',
                    'company_name' => 'Example Company',
                    'title' => 'Senior Symfony Developer',
                    'description' => '<p>PHP Symfony React API Platform</p>',
                    'remote' => false,
                    'url' => 'https://example.test/jobs/symfony',
                    'tags' => ['PHP', 'Symfony'],
                    'job_types' => ['full_time'],
                    'location' => 'Paris, France',
                    'created_at' => '2026-08-02T10:00:00+00:00',
                ],
                [
                    'slug' => 'creative-account-manager-remote',
                    'company_name' => 'Unrelated Company',
                    'title' => 'Creative Account Manager',
                    'description' => '<p>Creative interaction with international clients.</p>',
                    'remote' => true,
                    'url' => 'https://example.test/jobs/account-manager',
                    'tags' => ['Sales'],
                    'job_types' => ['full_time'],
                    'location' => 'Remote',
                    'created_at' => '2026-08-02T10:00:00+00:00',
                ],
            ],
            'links' => ['next' => null],
        ], JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['content-type: application/json'],
        ]));

        $provider = new ArbeitnowJobProvider($client, true, 1);
        self::assertSame('arbeitnow', $provider->code());
        self::assertSame('Arbeitnow', $provider->name());
        self::assertSame(ConnectorMode::API, $provider->mode());
        self::assertTrue($provider->isConfigured());
        self::assertNull($provider->configurationMessage());

        $offers = $provider->search(['Senior Symfony Developer'], ['PHP', 'Symfony', 'React']);

        self::assertCount(1, $offers);
        self::assertSame('Arbeitnow', $offers[0]['source']);
        self::assertSame('senior-symfony-paris', $offers[0]['externalId']);
        self::assertSame('Senior Symfony Developer', $offers[0]['title']);
        self::assertSame('Example Company', $offers[0]['company']);
        self::assertSame('Paris, France', $offers[0]['location']);
        self::assertSame('CDI', $offers[0]['contractType']);
        self::assertSame('PHP Symfony React API Platform', $offers[0]['description']);
    }

    public function testDisabledArbeitnowDoesNotCallNetwork(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new \LogicException('The HTTP client must not be called.');
        });

        $provider = new ArbeitnowJobProvider($client, false, 1);

        self::assertFalse($provider->isConfigured());
        self::assertStringContainsString('ARBEITNOW_ENABLED', (string) $provider->configurationMessage());
        self::assertSame([], $provider->search(['Symfony'], ['PHP']));
    }

    public function testAdzunaNormalizesFrenchSearchResult(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'results' => [[
                'id' => 'adzuna-123',
                'title' => 'Développeur PHP Symfony',
                'description' => 'Mission Symfony et React en environnement Docker.',
                'redirect_url' => 'https://example.test/adzuna-123',
                'created' => '2026-08-02T09:00:00Z',
                'salary_min' => 50000,
                'salary_max' => 60000,
                'contract_type' => 'permanent',
                'company' => ['display_name' => 'Entreprise Test'],
                'location' => ['display_name' => 'Cergy, Île-de-France'],
            ]],
        ], JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['content-type: application/json'],
        ]));

        $provider = new AdzunaJobProvider($client, 'app-id', 'app-key', 'fr', '', 20);
        self::assertSame('adzuna', $provider->code());
        self::assertSame('Adzuna', $provider->name());
        self::assertSame(ConnectorMode::API, $provider->mode());
        self::assertTrue($provider->isConfigured());
        self::assertNull($provider->configurationMessage());

        $offers = $provider->search(['Senior Symfony Developer'], ['PHP', 'Symfony']);

        self::assertCount(1, $offers);
        self::assertSame('Adzuna', $offers[0]['source']);
        self::assertSame('adzuna-123', $offers[0]['externalId']);
        self::assertSame('Développeur PHP Symfony', $offers[0]['title']);
        self::assertSame('Entreprise Test', $offers[0]['company']);
        self::assertSame(50000, $offers[0]['salaryMin']);
        self::assertSame(60000, $offers[0]['salaryMax']);
        self::assertSame('CDI', $offers[0]['contractType']);
    }

    public function testAdzunaExplainsMissingCredentials(): void
    {
        $provider = new AdzunaJobProvider(new MockHttpClient(), '', '');

        self::assertFalse($provider->isConfigured());
        self::assertStringContainsString('ADZUNA_APP_ID', (string) $provider->configurationMessage());
    }
}
