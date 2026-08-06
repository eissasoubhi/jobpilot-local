<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\Service\SmartRecruitersJobProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SmartRecruitersJobProviderTest extends TestCase
{
    public function testItRequiresTokenAndCompanyIdentifiersWithoutCallingTheNetwork(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new \LogicException('The HTTP client must not be called.');
        });
        $provider = new SmartRecruitersJobProvider($client);

        self::assertSame('smartrecruiters', $provider->code());
        self::assertSame('SmartRecruiters', $provider->name());
        self::assertSame(ConnectorMode::API, $provider->mode());
        self::assertFalse($provider->isConfigured());
        self::assertStringContainsString('SMARTRECRUITERS_API_TOKEN', (string) $provider->configurationMessage());
        self::assertStringContainsString('SMARTRECRUITERS_COMPANY_IDENTIFIERS', (string) $provider->configurationMessage());
        self::assertSame([], $provider->search(['Symfony'], ['PHP']));
    }

    public function testItListsPublicPostingsFiltersLocallyAndNormalizesTheDetailedPosting(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            if (count($requests) === 1) {
                return self::jsonResponse([
                    'limit' => 100,
                    'offset' => 0,
                    'totalFound' => 2,
                    'content' => [
                        [
                            'id' => 'posting-123',
                            'uuid' => 'uuid-123',
                            'name' => 'Développeur PHP Symfony',
                            'releasedDate' => '2026-08-05T08:30:00.000Z',
                            'company' => ['identifier' => 'acme', 'name' => 'Acme France'],
                            'location' => ['city' => 'Paris', 'region' => 'Île-de-France', 'country' => 'fr', 'remote' => true],
                            'department' => ['label' => 'Engineering'],
                            'function' => ['label' => 'Information Technology'],
                            'typeOfEmployment' => ['label' => 'Contract'],
                        ],
                        [
                            'id' => 'posting-sales',
                            'uuid' => 'uuid-sales',
                            'name' => 'Responsable commercial',
                            'company' => ['identifier' => 'acme', 'name' => 'Acme France'],
                            'location' => ['city' => 'Paris', 'country' => 'fr', 'remote' => false],
                            'department' => ['label' => 'Sales'],
                            'function' => ['label' => 'Sales'],
                            'typeOfEmployment' => ['label' => 'Full-time'],
                        ],
                    ],
                ]);
            }

            return self::jsonResponse([
                'id' => 'posting-123',
                'uuid' => 'uuid-123',
                'name' => 'Développeur PHP Symfony',
                'releasedDate' => '2026-08-05T08:30:00.000Z',
                'postingUrl' => 'https://jobs.smartrecruiters.com/acme/posting-123',
                'applyUrl' => 'https://jobs.smartrecruiters.com/acme/posting-123/apply',
                'company' => ['identifier' => 'acme', 'name' => 'Acme France'],
                'location' => ['city' => 'Paris', 'region' => 'Île-de-France', 'country' => 'fr', 'remote' => true],
                'typeOfEmployment' => ['label' => 'Contract'],
                'jobAd' => [
                    'sections' => [
                        'jobDescription' => ['text' => '<p>Développement Symfony 6 et API Platform.</p>'],
                        'qualifications' => ['text' => '<p>PHP 8, React et Docker.</p>'],
                        'additionalInformation' => ['text' => '<p>Mission longue en télétravail.</p>'],
                    ],
                ],
                'active' => true,
            ]);
        });

        $provider = new SmartRecruitersJobProvider($client, 'secret-token', 'acme', 1, 100, 20);
        $offers = $provider->search(['Senior Symfony Developer'], ['PHP', 'Symfony', 'React']);

        self::assertTrue($provider->isConfigured());
        self::assertNull($provider->configurationMessage());
        self::assertSame('2026-08-06.1', $provider->parserVersion());
        self::assertCount(2, $requests);
        self::assertSame('GET', $requests[0]['method']);
        self::assertStringContainsString('/v1/companies/acme/postings', $requests[0]['url']);
        self::assertStringContainsString('destination=PUBLIC', $requests[0]['url']);
        self::assertStringContainsString('limit=100', $requests[0]['url']);
        self::assertStringContainsString('/v1/companies/acme/postings/uuid-123', $requests[1]['url']);
        $smartTokenHeaders = $requests[0]['options']['normalized_headers']['x-smarttoken'] ?? [];
        self::assertStringContainsString('secret-token', implode(' ', $smartTokenHeaders));

        self::assertCount(1, $offers);
        self::assertSame('SmartRecruiters', $offers[0]['source']);
        self::assertSame('uuid-123', $offers[0]['externalId']);
        self::assertSame('Développeur PHP Symfony', $offers[0]['title']);
        self::assertSame('Acme France', $offers[0]['company']);
        self::assertSame('Paris, Île-de-France, fr', $offers[0]['location']);
        self::assertSame('CDD', $offers[0]['contractType']);
        self::assertSame('Télétravail', $offers[0]['workMode']);
        self::assertSame('https://jobs.smartrecruiters.com/acme/posting-123', $offers[0]['sourceUrl']);
        self::assertStringContainsString('Développement Symfony 6 et API Platform.', $offers[0]['description']);
        self::assertStringContainsString('PHP 8, React et Docker.', $offers[0]['description']);
        self::assertSame('2026-08-05T08:30:00+00:00', $offers[0]['publishedAt']);
    }

    public function testItsPolicyReflectsTheBoundedNumberOfListAndDetailRequests(): void
    {
        $provider = new SmartRecruitersJobProvider(
            new MockHttpClient(),
            'token',
            'acme, ACME;second invalid/company third fourth fifth sixth',
            9,
            999,
            999,
        );
        $policy = $provider->policy();

        self::assertSame(ConnectorComplianceStatus::AUTHORIZED_ONLY, $policy->complianceStatus);
        self::assertSame(30, $policy->maxRequestsPerSync);
        self::assertFalse($policy->respectsRobotsTxt);
    }

    public function testItRaisesAnExplicitErrorWhenTheListRequestIsUnauthorized(): void
    {
        $client = new MockHttpClient(new MockResponse('{}', [
            'http_code' => 401,
            'response_headers' => ['content-type: application/json'],
        ]));
        $provider = new SmartRecruitersJobProvider($client, 'invalid-token', 'acme');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('statut HTTP 401');
        $provider->search(['Symfony'], ['PHP']);
    }

    /** @param array<string, mixed> $payload */
    private static function jsonResponse(array $payload): MockResponse
    {
        return new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['content-type: application/json'],
        ]);
    }
}
