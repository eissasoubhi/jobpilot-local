<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\Service\FranceTravailJobProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FranceTravailJobProviderTest extends TestCase
{
    public function testOfficialApiAuthenticationAndOfferNormalization(): void
    {
        $requests = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): ResponseInterface {
            $requests[] = [$method, $url, $options];

            if ($method === 'POST') {
                return new MockResponse(json_encode([
                    'access_token' => 'test-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 1499,
                ], JSON_THROW_ON_ERROR), [
                    'http_code' => 200,
                    'response_headers' => ['content-type: application/json'],
                ]);
            }

            return new MockResponse(json_encode([
                'resultats' => [[
                    'id' => '187ABCD',
                    'intitule' => 'Développeur PHP Symfony',
                    'description' => '<p>Symfony, API Platform et React. Télétravail partiel.</p>',
                    'dateCreation' => '2026-08-05T10:15:00+02:00',
                    'typeContrat' => 'CDI',
                    'typeContratLibelle' => 'Contrat à durée indéterminée',
                    'natureContrat' => 'Contrat travail',
                    'entreprise' => ['nom' => 'Entreprise Exemple'],
                    'lieuTravail' => ['libelle' => '95 - CERGY'],
                    'salaire' => ['libelle' => 'Annuel de 50000.0 Euros à 60000.0 Euros sur 12.0 mois'],
                    'origineOffre' => ['urlOrigine' => 'https://candidat.francetravail.fr/offres/recherche/detail/187ABCD'],
                ]],
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 206,
                'response_headers' => ['content-type: application/json'],
            ]);
        });

        $provider = new FranceTravailJobProvider(
            $client,
            ' client-id ',
            " client-secret\n",
            'api_offresdemploiv2 o2dsoffre',
            'https://auth.example.test/token',
            'https://api.example.test/offres/search',
            50,
        );

        self::assertSame('france-travail', $provider->code());
        self::assertSame('France Travail', $provider->name());
        self::assertSame(ConnectorMode::API, $provider->mode());
        self::assertSame('offres-emploi-v2', $provider->parserVersion());
        self::assertSame(ConnectorComplianceStatus::AUTHORIZED_ONLY, $provider->policy()->complianceStatus);
        self::assertTrue($provider->isConfigured());
        self::assertNull($provider->configurationMessage());

        $offers = $provider->search(['Senior Symfony Developer'], ['PHP', 'Symfony']);

        self::assertCount(1, $offers);
        self::assertSame('France Travail', $offers[0]['source']);
        self::assertSame('187ABCD', $offers[0]['externalId']);
        self::assertSame('Développeur PHP Symfony', $offers[0]['title']);
        self::assertSame('Entreprise Exemple', $offers[0]['company']);
        self::assertSame('95 - CERGY', $offers[0]['location']);
        self::assertSame('CDI', $offers[0]['contractType']);
        self::assertSame('Hybride', $offers[0]['workMode']);
        self::assertSame(50000, $offers[0]['salaryMin']);
        self::assertSame(60000, $offers[0]['salaryMax']);
        self::assertSame('Symfony, API Platform et React. Télétravail partiel.', $offers[0]['description']);

        self::assertCount(2, $requests);
        self::assertSame('POST', $requests[0][0]);
        parse_str((string) $requests[0][2]['body'], $tokenFields);
        self::assertSame('client_credentials', $tokenFields['grant_type'] ?? null);
        self::assertSame('client-id', $tokenFields['client_id'] ?? null);
        self::assertSame('client-secret', $tokenFields['client_secret'] ?? null);
        self::assertSame('GET', $requests[1][0]);
        $headers = implode("\n", array_map('strval', $requests[1][2]['headers']));
        self::assertStringContainsString('Authorization: Bearer test-access-token', $headers);
        self::assertStringContainsString('Range: offres=0-49', $headers);
    }

    public function testNoContentForOneQueryContinuesWithTheNextQuery(): void
    {
        $searchRequests = 0;
        $client = new MockHttpClient(function (string $method) use (&$searchRequests): ResponseInterface {
            if ($method === 'POST') {
                return new MockResponse(json_encode([
                    'access_token' => 'test-access-token',
                ], JSON_THROW_ON_ERROR), [
                    'http_code' => 200,
                    'response_headers' => ['content-type: application/json'],
                ]);
            }

            ++$searchRequests;
            if ($searchRequests === 1) {
                return new MockResponse('', ['http_code' => 204]);
            }

            return new MockResponse(json_encode([
                'resultats' => [[
                    'id' => '204-FALLBACK',
                    'intitule' => 'Développeur Symfony',
                    'description' => 'Développement PHP et Symfony.',
                    'typeContrat' => 'CDI',
                    'typeContratLibelle' => 'CDI',
                ]],
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 206,
                'response_headers' => ['content-type: application/json'],
            ]);
        });

        $provider = new FranceTravailJobProvider($client, 'client-id', 'client-secret');
        $offers = $provider->search(
            ['Senior Symfony Developer', 'Backend PHP Developer'],
            ['PHP', 'Symfony'],
        );

        self::assertSame(2, $searchRequests);
        self::assertCount(1, $offers);
        self::assertSame('204-FALLBACK', $offers[0]['externalId']);
    }

    public function testAllNoContentResponsesReturnAnEmptySuccessfulResult(): void
    {
        $searchRequests = 0;
        $client = new MockHttpClient(function (string $method) use (&$searchRequests): ResponseInterface {
            if ($method === 'POST') {
                return new MockResponse(json_encode([
                    'access_token' => 'test-access-token',
                ], JSON_THROW_ON_ERROR), [
                    'http_code' => 200,
                    'response_headers' => ['content-type: application/json'],
                ]);
            }

            ++$searchRequests;

            return new MockResponse('', ['http_code' => 204]);
        });

        $provider = new FranceTravailJobProvider($client, 'client-id', 'client-secret');

        self::assertSame([], $provider->search(
            ['Senior Symfony Developer', 'Backend PHP Developer'],
            ['PHP', 'Symfony'],
        ));
        self::assertSame(2, $searchRequests);
    }

    public function testMissingCredentialsPreventEveryNetworkRequest(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new \LogicException('The HTTP client must not be called without credentials.');
        });
        $provider = new FranceTravailJobProvider($client);

        self::assertFalse($provider->isConfigured());
        self::assertStringContainsString('FRANCE_TRAVAIL_CLIENT_ID', (string) $provider->configurationMessage());
        self::assertSame([], $provider->search(['Symfony'], ['PHP']));
    }

    public function testMissingAccessTokenFailsExplicitly(): void
    {
        $client = new MockHttpClient(new MockResponse('{}', [
            'http_code' => 200,
            'response_headers' => ['content-type: application/json'],
        ]));
        $provider = new FranceTravailJobProvider($client, 'client-id', 'client-secret');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('aucun jeton');
        $provider->search(['Symfony'], ['PHP']);
    }

    public function testInvalidScopeProvidesAnActionableSafeDiagnostic(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'error' => 'invalid_scope',
            'error_description' => "Le scope demandé n'est pas autorisé.\nVérifiez votre souscription.",
        ], JSON_THROW_ON_ERROR), [
            'http_code' => 400,
            'response_headers' => ['content-type: application/json'],
        ]));
        $provider = new FranceTravailJobProvider($client, 'client-id', 'super-secret-value');

        try {
            $provider->search(['Symfony'], ['PHP']);
            self::fail('The authentication request should have failed.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('HTTP 400', $exception->getMessage());
            self::assertStringContainsString('invalid_scope', $exception->getMessage());
            self::assertStringContainsString('API Offres d’emploi est rattachée et active', $exception->getMessage());
            self::assertStringNotContainsString('super-secret-value', $exception->getMessage());
            self::assertStringNotContainsString("\n", $exception->getMessage());
        }
    }

    public function testInvalidClientProvidesCredentialGuidanceWithoutEchoingTheSecret(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'error' => 'invalid_client',
            'error_description' => 'Client authentication failed.',
        ], JSON_THROW_ON_ERROR), [
            'http_code' => 400,
            'response_headers' => ['content-type: application/json'],
        ]));
        $provider = new FranceTravailJobProvider($client, 'client-id', 'private-secret');

        try {
            $provider->search(['Symfony'], ['PHP']);
            self::fail('The authentication request should have failed.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('invalid_client', $exception->getMessage());
            self::assertStringContainsString('même application France Travail.io', $exception->getMessage());
            self::assertStringNotContainsString('private-secret', $exception->getMessage());
        }
    }
}
