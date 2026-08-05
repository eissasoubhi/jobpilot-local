<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CanonicalJobCatalogTest extends WebTestCase
{
    public function testSameOfferFromTwoSourcesProducesOneCanonicalCard(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(5));
        $company = 'Canonical Company '.$suffix;

        $client->jsonRequest('POST', '/api/jobs', [
            'source' => 'Source Alpha',
            'sourceCode' => 'source-alpha-'.$suffix,
            'sourceUrl' => 'https://alpha.example/jobs/'.$suffix.'?utm_source=email',
            'title' => 'Senior PHP Symfony React Developer '.$suffix,
            'company' => $company,
            'location' => 'Paris',
            'contractType' => 'Freelance',
            'workMode' => 'Hybride',
            'description' => 'Mission senior PHP Symfony React API Platform Docker pour une application métier.',
            'publishedAt' => (new \DateTimeImmutable('-2 days'))->format(DATE_ATOM),
        ]);
        self::assertResponseStatusCodeSame(201);
        $first = $this->decode($client->getResponse()->getContent());
        self::assertSame(1, $first['sourceCount']);
        self::assertSame('Source Alpha', $first['sources'][0]['sourceName']);

        $client->jsonRequest('POST', '/api/jobs', [
            'source' => 'Source Beta',
            'sourceCode' => 'source-beta-'.$suffix,
            'sourceUrl' => 'https://beta.example/offres/'.$suffix,
            'title' => 'Développeur senior React Symfony PHP '.$suffix,
            'company' => $company,
            'location' => 'Paris',
            'contractType' => 'Freelance',
            'workMode' => 'Hybride',
            'description' => 'Description détaillée de la même mission Symfony React PHP avec Docker et API Platform.',
            'publishedAt' => (new \DateTimeImmutable('-1 day'))->format(DATE_ATOM),
        ]);
        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(200);
        $merged = $this->decode($client->getResponse()->getContent());

        self::assertSame($first['id'], $merged['id']);
        self::assertSame(2, $merged['sourceCount']);
        self::assertSame(
            ['Source Alpha', 'Source Beta'],
            array_column($merged['sources'], 'sourceName'),
        );
        self::assertSame('SIMILARITY', $merged['sources'][1]['matchType']);
        self::assertGreaterThanOrEqual(84, $merged['sources'][1]['matchScore']);

        $client->jsonRequest('POST', '/api/jobs', [
            'source' => 'Source Alpha',
            'sourceCode' => 'source-alpha-'.$suffix,
            'sourceUrl' => 'https://alpha.example/jobs/'.$suffix.'?utm_source=second-alert',
            'title' => 'Senior PHP Symfony React Developer '.$suffix,
            'company' => $company,
            'description' => 'Même occurrence reçue une seconde fois.',
        ]);
        self::assertResponseStatusCodeSame(200);
        $duplicate = $this->decode($client->getResponse()->getContent());
        self::assertSame($first['id'], $duplicate['id']);
        self::assertSame(2, $duplicate['sourceCount']);

        $client->request('GET', '/api/jobs');
        self::assertResponseIsSuccessful();
        $jobs = $this->decode($client->getResponse()->getContent());
        $matching = array_values(array_filter(
            $jobs,
            static fn (array $job): bool => $job['company'] === $company,
        ));
        self::assertCount(1, $matching);
    }

    /** @return array<string|int, mixed> */
    private function decode(string|false $content): array
    {
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
