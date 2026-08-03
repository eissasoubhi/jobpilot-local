<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ApiWorkflowTest extends WebTestCase
{
    public function testCompleteLocalWorkflow(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/health');
        self::assertResponseIsSuccessful();
        $health = $this->decodeResponse($client);
        self::assertSame('ok', $health['status']);

        $client->request('GET', '/api/profile');
        self::assertResponseIsSuccessful();
        $profile = $this->decodeResponse($client);
        self::assertSame('Aissa SOUBHI', $profile['fullName']);

        $temporaryCv = tempnam(sys_get_temp_dir(), 'jobpilot-cv-');
        self::assertIsString($temporaryCv);
        file_put_contents($temporaryCv, "%PDF-1.4\n% JobPilot functional test CV\n%%EOF\n");
        $uploadedCv = new UploadedFile($temporaryCv, 'cv-symfony-react-fr.pdf', 'application/pdf', null, true);

        $client->request('POST', '/api/cvs', [
            'name' => 'CV Symfony React FR',
            'language' => 'fr',
            'category' => 'Full-Stack',
            'tags' => 'Symfony, React, PHP',
            'defaultForLanguage' => 'true',
        ], [
            'file' => $uploadedCv,
        ]);
        self::assertResponseStatusCodeSame(201);
        $cv = $this->decodeResponse($client);
        self::assertSame('CV Symfony React FR', $cv['name']);
        self::assertSame('fr', $cv['language']);
        self::assertSame(['Symfony', 'React', 'PHP'], $cv['tags']);
        self::assertTrue($cv['defaultForLanguage']);

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
        $job = $this->decodeResponse($client);
        self::assertSame('fr', $job['language']);
        self::assertSame(520, $job['proposedTjm']);
        self::assertSame('PREPARED', $job['status']);
        self::assertGreaterThanOrEqual(50, $job['score']);
        self::assertSame('CV Symfony React FR', $job['recommendedCv']['name']);

        $client->request('GET', '/api/applications');
        self::assertResponseIsSuccessful();
        $applications = $this->decodeResponse($client);
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
        $englishJob = $this->decodeResponse($client);
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
        $rejectedJob = $this->decodeResponse($client);
        self::assertSame('REJECTED_BY_FILTER', $rejectedJob['status']);

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
        $createdPositioning = $this->decodeResponse($client);
        self::assertSame(450, $createdPositioning['proposedTjm']);

        $client->jsonRequest('POST', '/api/positionings', [
            ...$positioning,
            'agency' => 'Agence B',
        ]);
        self::assertResponseStatusCodeSame(409);

        $client->request('GET', '/api/dashboard');
        self::assertResponseIsSuccessful();
        $dashboard = $this->decodeResponse($client);
        self::assertGreaterThanOrEqual(3, $dashboard['counts']['jobs']);
        self::assertGreaterThanOrEqual(1, $dashboard['counts']['positionings']);
    }

    /**
     * @return array<string|int, mixed>
     */
    private function decodeResponse(object $client): array
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
