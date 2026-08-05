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
            'client-id',
            'client-secret',
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
        self::assertSame('client_credentials', $requests[0][2]['body']['grant_type']);
        self::assertSame('client-id', $requests[0][2]['body']['client_id']);
        self::assertSame('GET', $requests[1][0]);
        self::assertSame('Bearer test-access-token', $requests[1][2]['headers']['Authorization']);
        self::assertSame('offres=0-49', $requests[1][2]['headers']['Range']);
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
}
