<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AutofillCorrectionTest extends WebTestCase
{
    public function testCorrectionCanBeConfirmedLoadedUpdatedAndDeleted(): void
    {
        $client = static::createClient();

        $payload = [
            'host' => 'jobs.example.test',
            'fieldFingerprint' => 'select|location||work location|',
            'canonicalKey' => 'address.city',
            'controlKind' => 'select',
            'originalValue' => 'Paris',
            'correctedValue' => 'Cergy',
        ];

        $client->jsonRequest('POST', '/api/autofill/corrections', $payload);
        self::assertResponseStatusCodeSame(201);
        $created = $this->decode($client);
        self::assertSame('jobs.example.test', $created['host']);
        self::assertSame('Cergy', $created['correctedValue']);
        self::assertTrue($created['enabled']);

        $client->request('GET', '/api/autofill/corrections', ['host' => 'jobs.example.test']);
        self::assertResponseIsSuccessful();
        $list = $this->decode($client);
        self::assertCount(1, $list);
        self::assertSame($created['id'], $list[0]['id']);

        $client->jsonRequest('POST', '/api/autofill/corrections', [
            ...$payload,
            'correctedValue' => 'Lyon',
        ]);
        self::assertResponseIsSuccessful();
        $updated = $this->decode($client);
        self::assertSame($created['id'], $updated['id']);
        self::assertSame('Lyon', $updated['correctedValue']);

        $client->jsonRequest('PATCH', '/api/autofill/corrections/'.$created['id'], ['enabled' => false]);
        self::assertResponseIsSuccessful();
        self::assertFalse($this->decode($client)['enabled']);

        $client->request('GET', '/api/autofill/corrections', ['host' => 'jobs.example.test']);
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->decode($client));

        $client->request('DELETE', '/api/autofill/corrections/'.$created['id']);
        self::assertResponseStatusCodeSame(204);
    }

    public function testSensitiveCompensationAndFreeTextCorrectionsAreRejected(): void
    {
        $client = static::createClient();
        $base = [
            'host' => 'jobs.example.test',
            'fieldFingerprint' => 'select|field|||',
            'controlKind' => 'select',
            'originalValue' => 'A',
            'correctedValue' => 'B',
        ];

        $client->jsonRequest('POST', '/api/autofill/corrections', [
            ...$base,
            'canonicalKey' => 'screening.workAuthorisation',
        ]);
        self::assertResponseStatusCodeSame(400);

        $client->jsonRequest('POST', '/api/autofill/corrections', [
            ...$base,
            'canonicalKey' => 'preferences.desiredSalary',
        ]);
        self::assertResponseStatusCodeSame(400);

        $client->jsonRequest('POST', '/api/autofill/corrections', [
            ...$base,
            'canonicalKey' => 'professional.currentJobTitle',
            'controlKind' => 'text',
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    /** @return array<string|int, mixed> */
    private function decode(object $client): array
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
