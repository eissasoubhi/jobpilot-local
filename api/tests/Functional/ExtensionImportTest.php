<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ExtensionImportTest extends WebTestCase
{
    public function testExtensionImportUsesCanonicalSourceOccurrenceAndIsIdempotent(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(5));
        $url = 'https://www.free-work.com/fr/tech-it/job-mission/php/test-'.$suffix;
        $payload = [
            'url' => $url,
            'source' => 'Free-Work',
            'sourceCode' => 'free-work',
            'externalId' => 'free-work-test-'.$suffix,
            'title' => 'Développeur Senior PHP Symfony '.$suffix,
            'company' => 'Extension Company '.$suffix,
            'location' => 'Île-de-France, France',
            'contractType' => 'Freelance',
            'workMode' => 'Hybride',
            'description' => 'Mission PHP 8 Symfony API Platform PostgreSQL Docker avec architecture hexagonale.',
            'publishedAt' => (new \DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            'tjmMin' => 450,
            'tjmMax' => 520,
            'extractionMethod' => 'job-posting-json-ld',
        ];

        $client->jsonRequest('POST', '/api/extension/import-page', $payload);
        self::assertResponseStatusCodeSame(201);
        $first = $this->decode($client->getResponse()->getContent());

        self::assertSame('Free-Work', $first['source']);
        self::assertSame('free-work', $first['sourceCode']);
        self::assertSame(1, $first['sourceCount']);
        self::assertSame('free-work', $first['sources'][0]['sourceCode']);
        self::assertSame('PRIMARY', $first['sources'][0]['matchType']);
        self::assertSame(450, $first['tjmMin']);
        self::assertSame(520, $first['tjmMax']);

        $client->jsonRequest('POST', '/api/extension/import-page', [
            ...$payload,
            'description' => 'Même offre importée une seconde fois depuis la page visible.',
        ]);
        self::assertResponseStatusCodeSame(200);
        $second = $this->decode($client->getResponse()->getContent());

        self::assertSame($first['id'], $second['id']);
        self::assertSame(1, $second['sourceCount']);

        $client->request('GET', '/api/jobs');
        self::assertResponseIsSuccessful();
        $jobs = $this->decode($client->getResponse()->getContent());
        $matching = array_values(array_filter(
            $jobs,
            static fn (array $job): bool => $job['company'] === $payload['company'],
        ));
        self::assertCount(1, $matching);
    }

    public function testExtensionImportRejectsInvalidUrl(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/api/extension/import-page', [
            'url' => 'not-a-url',
            'title' => 'Offre invalide',
            'description' => 'Cette offre ne doit pas être importée.',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /** @return array<string|int, mixed> */
    private function decode(string|false $content): array
    {
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
