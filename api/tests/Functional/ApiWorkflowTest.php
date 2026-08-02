<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiWorkflowTest extends WebTestCase
{
    public function testCompleteLocalWorkflow(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/health');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['status' => 'ok']);

        $client->request('GET', '/api/profile');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['fullName' => 'Aissa SOUBHI']);

        $client->jsonRequest('POST', '/api/jobs', [
            'source' => 'Test',
            'title' => 'Senior Symfony React Developer',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'Freelance',
            'workMode' => 'Hybride',
            'description' => 'Nous recherchons un développeur senior PHP Symfony React TypeScript Docker API Platform avec 11 ans d’expérience.',
            'publishedAt' => (new \DateTimeImmutable('-2 hours'))->format(DATE_ATOM),
            'tjmMin' => 480,
            'tjmMax' => 600,
        ]);
        self::assertResponseStatusCodeSame(201);
        $job = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('fr', $job['language']);
        self::assertSame(520, $job['proposedTjm']);
        self::assertSame('PREPARED', $job['status']);
        self::assertGreaterThanOrEqual(50, $job['score']);

        $client->request('GET', '/api/applications');
        self::assertResponseIsSuccessful();
        $applications = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($applications);
        self::assertStringContainsString('Bonjour', $applications[0]['message']);

        $client->jsonRequest('POST', '/api/jobs', [
            'source' => 'Test',
            'title' => 'Senior Full Stack Engineer',
            'company' => 'English Company',
            'location' => 'Lyon',
            'contractType' => 'CDI',
            'workMode' => 'Remote',
            'description' => 'We are looking for a senior PHP Symfony and React engineer with strong Docker and TypeScript experience.',
            'salaryMin' => 60_000,
            'salaryMax' => 65_000,
        ]);
        self::assertResponseStatusCodeSame(201);
        $englishJob = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('en', $englishJob['language']);
        self::assertSame(60_000, $englishJob['proposedSalary']);

        $client->jsonRequest('POST', '/api/jobs', [
            'source' => 'Test',
            'title' => 'Stage développeur PHP',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'Stage',
            'workMode' => 'Hybride',
            'description' => 'Stage en développement PHP Symfony.',
        ]);
        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['status' => 'REJECTED_BY_FILTER']);

        $positioning = [
            'finalClient' => 'France Télévisions',
            'agency' => 'Agence A',
            'recruiterName' => 'Jean Dupont',
            'recruiterEmail' => 'jean@example.test',
            'missionTitle' => 'Développeur Symfony React',
            'description' => 'Mission Symfony React pour la Video Factory.',
            'callForTenderReference' => 'AO-TEST-001',
            'advertisedTjmFixed' => 450,
            'location' => 'Paris',
            'remotePolicy' => 'Hybride',
            'status' => 'AGREEMENT_GIVEN',
        ];

        $client->jsonRequest('POST', '/api/positionings', $positioning);
        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['proposedTjm' => 450]);

        $client->jsonRequest('POST', '/api/positionings', [
            ...$positioning,
            'agency' => 'Agence B',
        ]);
        self::assertResponseStatusCodeSame(409);

        $client->request('GET', '/api/dashboard');
        self::assertResponseIsSuccessful();
        $dashboard = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertGreaterThanOrEqual(3, $dashboard['counts']['jobs']);
        self::assertGreaterThanOrEqual(1, $dashboard['counts']['positionings']);
    }
}
