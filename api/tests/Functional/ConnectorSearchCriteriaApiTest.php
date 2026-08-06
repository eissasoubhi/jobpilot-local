<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ConnectorSyncRun;
use App\Entity\SourceConnector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConnectorSearchCriteriaApiTest extends WebTestCase
{
    public function testFranceTravailCriteriaCanBeReadUpdatedAndReflectedInSettings(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/connectors/france-travail/criteria');
        self::assertResponseIsSuccessful();
        $initial = $this->decode($client->getResponse()->getContent());

        self::assertSame('france-travail', $initial['code']);
        self::assertSame('France Travail', $initial['name']);
        self::assertSame('GLOBAL', $initial['scope']);
        self::assertIsArray($initial['targetJobs']);
        self::assertIsArray($initial['skills']);
        self::assertIsArray($initial['effectiveQueries']);
        self::assertArrayHasKey('latestSearchDiagnostics', $initial);
        self::assertSame(6, $initial['limits']['maxEffectiveQueries']);
        self::assertStringContainsString('critères globaux', $initial['note']);

        $client->jsonRequest('PUT', '/api/connectors/france-travail/criteria', [
            'targetJobs' => [
                ' Senior Symfony Developer ',
                'Backend PHP/Symfony',
                'senior symfony developer',
            ],
            'skills' => [' PHP ', 'Symfony', 'PHP'],
        ]);
        self::assertResponseIsSuccessful();
        $updated = $this->decode($client->getResponse()->getContent());

        self::assertSame(
            ['Senior Symfony Developer', 'Backend PHP/Symfony'],
            $updated['targetJobs'],
        );
        self::assertSame(['PHP', 'Symfony'], $updated['skills']);
        self::assertSame(['Symfony', 'Backend PHP Symfony'], $updated['effectiveQueries']);
        self::assertSame('Offres les plus récentes', $updated['fixedCriteria'][0]['value']);

        $client->request('GET', '/api/settings');
        self::assertResponseIsSuccessful();
        $settings = $this->decode($client->getResponse()->getContent());
        self::assertSame($updated['targetJobs'], $settings['targetJobs']);
        self::assertSame($updated['skills'], $settings['skills']);
    }

    public function testLatestDiagnosticsAreExposedAndMarkedStaleAfterCriteriaChange(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/connectors');
        self::assertResponseIsSuccessful();

        $client->jsonRequest('PUT', '/api/connectors/france-travail/criteria', [
            'targetJobs' => ['Senior Symfony Developer', 'Backend PHP Developer'],
            'skills' => ['PHP', 'Symfony'],
        ]);
        self::assertResponseIsSuccessful();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $connector = $entityManager->getRepository(SourceConnector::class)->findOneBy([
            'code' => 'france-travail',
        ]);
        self::assertInstanceOf(SourceConnector::class, $connector);

        $run = new ConnectorSyncRun($connector, 'manual');
        $run->complete(1, 1, 0, 0, 0, null, [
            'searchDiagnostics' => [
                'requestedQueries' => 2,
                'completedQueries' => 2,
                'queriesWithResults' => 1,
                'queriesWithoutResults' => 1,
                'received' => 1,
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
                        'received' => 1,
                        'uniqueOffersAdded' => 1,
                    ],
                ],
            ],
        ]);
        $entityManager->persist($run);
        $entityManager->flush();

        $client->request('GET', '/api/connectors/france-travail/criteria');
        self::assertResponseIsSuccessful();
        $current = $this->decode($client->getResponse()->getContent());
        self::assertTrue($current['latestSearchDiagnostics']['matchesCurrentCriteria']);
        self::assertSame(1, $current['latestSearchDiagnostics']['uniqueOffers']);
        self::assertSame('Symfony', $current['latestSearchDiagnostics']['queries'][0]['query']);
        self::assertNotSame('', $current['latestSearchDiagnostics']['startedAt']);

        $client->jsonRequest('PUT', '/api/connectors/france-travail/criteria', [
            'targetJobs' => ['React Developer'],
            'skills' => ['React'],
        ]);
        self::assertResponseIsSuccessful();
        $changed = $this->decode($client->getResponse()->getContent());
        self::assertSame(['React'], $changed['effectiveQueries']);
        self::assertFalse($changed['latestSearchDiagnostics']['matchesCurrentCriteria']);
    }

    public function testInvalidCriteriaAreRejectedWithoutClearingCurrentSettings(): void
    {
        $client = static::createClient();

        $client->jsonRequest('PUT', '/api/connectors/france-travail/criteria', [
            'targetJobs' => [],
            'skills' => ['x'],
        ]);
        self::assertResponseStatusCodeSame(422);
        $payload = $this->decode($client->getResponse()->getContent());
        self::assertStringContainsString('au moins un intitulé', $payload['error']);

        $client->request('GET', '/api/connectors/arbeitnow/criteria');
        self::assertResponseStatusCodeSame(404);
    }

    /** @return array<string|int, mixed> */
    private function decode(string|false $content): array
    {
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
