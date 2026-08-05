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

    public function testConflictingContractAndLocationKeepSeparateCanonicalOffers(): void
    {
        $client = static::createClient();
        $suffix = bin2hex(random_bytes(5));
        $company = 'Multiple Vacancies '.$suffix;
        $title = 'Développeur PHP Symfony '.$suffix;

        $client->jsonRequest('POST', '/api/jobs', [
            'source' => 'Source One',
            'sourceCode' => 'source-one-'.$suffix,
            'sourceUrl' => 'https://one.example/jobs/'.$suffix,
            'title' => $title,
            'company' => $company,
            'location' => 'Paris',
            'contractType' => 'CDI',
            'description' => 'Poste PHP Symfony à Paris en CDI.',
            'publishedAt' => (new \DateTimeImmutable('-2 days'))->format(DATE_ATOM),
        ]);
        self::assertResponseStatusCodeSame(201);
        $first = $this->decode($client->getResponse()->getContent());

        $client->jsonRequest('POST', '/api/jobs', [
            'source' => 'Source Two',
            'sourceCode' => 'source-two-'.$suffix,
            'sourceUrl' => 'https://two.example/jobs/'.$suffix,
            'title' => $title,
            'company' => $company,
            'location' => 'Lyon',
            'contractType' => 'Freelance',
            'description' => 'Mission PHP Symfony distincte à Lyon en freelance.',
            'publishedAt' => (new \DateTimeImmutable('-1 day'))->format(DATE_ATOM),
        ]);
        self::assertResponseStatusCodeSame(201);
        $second = $this->decode($client->getResponse()->getContent());

        self::assertNotSame($first['id'], $second['id']);
        self::assertSame(1, $first['sourceCount']);
        self::assertSame(1, $second['sourceCount']);
    }

    public function testAnySingleStrongConflictPreventsApproximateMerge(): void
    {
        $client = static::createClient();
        $scenarios = [
            'contract' => [
                'first' => ['contractType' => 'CDI', 'location' => 'Paris', 'publishedAt' => '-2 days'],
                'second' => ['contractType' => 'Freelance', 'location' => 'Paris', 'publishedAt' => '-1 day'],
            ],
            'location' => [
                'first' => ['contractType' => 'CDI', 'location' => 'Paris', 'publishedAt' => '-2 days'],
                'second' => ['contractType' => 'CDI', 'location' => 'Lyon', 'publishedAt' => '-1 day'],
            ],
            'date' => [
                'first' => ['contractType' => 'CDI', 'location' => 'Paris', 'publishedAt' => '-70 days'],
                'second' => ['contractType' => 'CDI', 'location' => 'Paris', 'publishedAt' => '-1 day'],
            ],
        ];

        foreach ($scenarios as $name => $scenario) {
            $suffix = $name.'-'.bin2hex(random_bytes(5));
            $company = 'Strong Conflict Company '.$suffix;
            $title = 'Développeur PHP Symfony '.$suffix;

            $client->jsonRequest('POST', '/api/jobs', [
                'source' => 'Source First',
                'sourceCode' => 'source-first-'.$suffix,
                'sourceUrl' => 'https://first.example/jobs/'.$suffix,
                'title' => $title,
                'company' => $company,
                'location' => $scenario['first']['location'],
                'contractType' => $scenario['first']['contractType'],
                'description' => 'Première offre pour vérifier un conflit métier fort.',
                'publishedAt' => (new \DateTimeImmutable($scenario['first']['publishedAt']))->format(DATE_ATOM),
            ]);
            self::assertResponseStatusCodeSame(201);
            $first = $this->decode($client->getResponse()->getContent());

            $client->jsonRequest('POST', '/api/jobs', [
                'source' => 'Source Second',
                'sourceCode' => 'source-second-'.$suffix,
                'sourceUrl' => 'https://second.example/jobs/'.$suffix,
                'title' => $title,
                'company' => $company,
                'location' => $scenario['second']['location'],
                'contractType' => $scenario['second']['contractType'],
                'description' => 'Deuxième offre qui doit rester distincte à cause d’un seul conflit métier.',
                'publishedAt' => (new \DateTimeImmutable($scenario['second']['publishedAt']))->format(DATE_ATOM),
            ]);
            self::assertResponseStatusCodeSame(201, sprintf('Le conflit %s doit empêcher la fusion.', $name));
            $second = $this->decode($client->getResponse()->getContent());

            self::assertNotSame($first['id'], $second['id'], sprintf('Le conflit %s a fusionné deux offres distinctes.', $name));
            self::assertSame(1, $first['sourceCount']);
            self::assertSame(1, $second['sourceCount']);
        }
    }

    /** @return array<string|int, mixed> */
    private function decode(string|false $content): array
    {
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
