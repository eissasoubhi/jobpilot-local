<?php

declare(strict_types=1);

namespace App\Tests\Functional;

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
            ['Senior Symfony Developer', 'Backend PHP/Symfony', 'senior symfony developer'],
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
