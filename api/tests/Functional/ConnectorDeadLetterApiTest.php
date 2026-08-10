<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\JobDiscovery\Application\ConnectorDeadLetterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConnectorDeadLetterApiTest extends WebTestCase
{
    public function testRepeatedFailureBecomesVisibleWithoutLeakingUrlQueryAndCanBeResolved(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $service = $container->get(ConnectorDeadLetterService::class);
        $em = $container->get(EntityManagerInterface::class);
        self::assertInstanceOf(ConnectorDeadLetterService::class, $service);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $connectorCode = 'dlq-functional-'.substr(hash('sha256', (string) microtime(true)), 0, 12);
        $payload = [
            'externalId' => 'JOB-42',
            'sourceUrl' => 'https://jobs.example.test/jobs/42?trackingId=secret-token&utm_source=email#private-fragment',
            'title' => 'Développeur PHP Symfony',
            'description' => 'Description volontairement invalide pour le test de la DLQ.',
            'rawData' => ['shouldNeverBeStored' => 'secret'],
        ];

        for ($failure = 1; $failure <= 3; ++$failure) {
            $service->recordPayloadFailure(
                $connectorCode,
                $payload,
                new \RuntimeException('Échec de normalisation '.$failure),
            );
        }
        $em->flush();

        $client->request('GET', '/api/connectors/dead-letters?state=OPEN&limit=200');
        self::assertResponseIsSuccessful();
        $entries = $this->decode($client->getResponse()->getContent());
        $entry = $this->findConnector($entries, $connectorCode);

        self::assertSame('OPEN', $entry['state']);
        self::assertSame(3, $entry['failureCount']);
        self::assertSame('IMPORT', $entry['stage']);
        self::assertSame('JOB-42', $entry['externalId']);
        self::assertSame('https://jobs.example.test/jobs/42', $entry['sourceUrl']);
        self::assertStringNotContainsString('secret-token', json_encode($entry, JSON_THROW_ON_ERROR));
        self::assertArrayNotHasKey('rawData', $entry);
        self::assertArrayNotHasKey('payload', $entry);

        $client->request('POST', '/api/connectors/dead-letters/'.$entry['id'].'/resolve');
        self::assertResponseIsSuccessful();
        $resolved = $this->decode($client->getResponse()->getContent());
        self::assertSame('RESOLVED', $resolved['state']);
        self::assertNotNull($resolved['resolvedAt']);

        $client->request('GET', '/api/connectors/dead-letters?state=OPEN&limit=200');
        self::assertResponseIsSuccessful();
        $openEntries = $this->decode($client->getResponse()->getContent());
        self::assertNull($this->findConnectorOrNull($openEntries, $connectorCode));
    }

    public function testInvalidDeadLetterStateIsRejected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/connectors/dead-letters?state=UNKNOWN');

        self::assertResponseStatusCodeSame(400);
        $payload = $this->decode($client->getResponse()->getContent());
        self::assertStringContainsString('invalide', mb_strtolower((string) $payload['error']));
    }

    /** @param list<array<string, mixed>> $entries @return array<string, mixed> */
    private function findConnector(array $entries, string $connectorCode): array
    {
        $entry = $this->findConnectorOrNull($entries, $connectorCode);
        self::assertNotNull($entry, 'La dead-letter attendue est absente de la réponse API.');

        return $entry;
    }

    /** @param list<array<string, mixed>> $entries @return array<string, mixed>|null */
    private function findConnectorOrNull(array $entries, string $connectorCode): ?array
    {
        foreach ($entries as $entry) {
            if (($entry['connectorCode'] ?? null) === $connectorCode) {
                return $entry;
            }
        }

        return null;
    }

    /** @return array<string|int, mixed> */
    private function decode(string|false $content): array
    {
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
