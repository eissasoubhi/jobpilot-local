<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\FranceTravailJobProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FranceTravailSearchDiagnosticsTest extends TestCase
{
    public function testDiagnosticsExplainEmptyAndSuccessfulQueriesWithoutExtraRequests(): void
    {
        $searchRequest = 0;
        $client = new MockHttpClient(function (string $method) use (&$searchRequest): ResponseInterface {
            if ($method === 'POST') {
                return new MockResponse(json_encode([
                    'access_token' => 'test-token',
                ], JSON_THROW_ON_ERROR), [
                    'http_code' => 200,
                    'response_headers' => ['content-type: application/json'],
                ]);
            }

            ++$searchRequest;
            if ($searchRequest === 1) {
                return new MockResponse('', ['http_code' => 204]);
            }

            return new MockResponse(json_encode([
                'resultats' => [
                    [
                        'id' => 'FT-1',
                        'intitule' => 'Développeur PHP Symfony',
                        'description' => 'Symfony et API Platform.',
                        'typeContrat' => 'CDI',
                        'typeContratLibelle' => 'CDI',
                    ],
                    [
                        'id' => 'FT-1',
                        'intitule' => 'Développeur PHP Symfony',
                        'description' => 'Occurrence répétée dans la même réponse.',
                        'typeContrat' => 'CDI',
                        'typeContratLibelle' => 'CDI',
                    ],
                ],
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

        self::assertCount(1, $offers);
        self::assertSame(2, $searchRequest);
        self::assertSame([
            'requestedQueries' => 2,
            'completedQueries' => 2,
            'queriesWithResults' => 1,
            'queriesWithoutResults' => 1,
            'received' => 2,
            'uniqueOffers' => 1,
            'queries' => [
                [
                    'query' => 'Symfony',
                    'statusCode' => 204,
                    'outcome' => 'NO_RESULTS',
                    'received' => 0,
                    'uniqueOffersAdded' => 0,
                ],
                [
                    'query' => 'Backend PHP',
                    'statusCode' => 206,
                    'outcome' => 'RESULTS',
                    'received' => 2,
                    'uniqueOffersAdded' => 1,
                ],
            ],
        ], $provider->searchDiagnostics());
    }

    public function testUnexpectedHttpStatusIsRecordedBeforeTheFailureIsRaised(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode(['access_token' => 'test-token'], JSON_THROW_ON_ERROR), [
                'http_code' => 200,
                'response_headers' => ['content-type: application/json'],
            ]),
            new MockResponse('', ['http_code' => 429]),
        ]);
        $provider = new FranceTravailJobProvider($client, 'client-id', 'client-secret');

        try {
            $provider->search(['Symfony'], ['PHP']);
            self::fail('The search should fail on HTTP 429.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('HTTP 429', $exception->getMessage());
        }

        $diagnostics = $provider->searchDiagnostics();
        self::assertSame(1, $diagnostics['requestedQueries']);
        self::assertSame(1, $diagnostics['completedQueries']);
        self::assertSame(0, $diagnostics['queriesWithResults']);
        self::assertSame(0, $diagnostics['queriesWithoutResults']);
        self::assertSame('ERROR', $diagnostics['queries'][0]['outcome']);
        self::assertSame(429, $diagnostics['queries'][0]['statusCode']);
        self::assertArrayNotHasKey('clientSecret', $diagnostics);
    }
}
