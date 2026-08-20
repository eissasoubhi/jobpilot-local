<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Timeline\JobTimelineEventType;
use App\Timeline\JobTimelineRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApplicationGoalApiTest extends WebTestCase
{
    public function testGoalsCanBeConfiguredAndTrackSubmittedTimelineEvents(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $timeline = static::getContainer()->get(JobTimelineRecorder::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(JobTimelineRecorder::class, $timeline);

        $client->jsonRequest('PUT', '/api/application-goals', [
            'daily' => 100,
            'weekly' => 500,
            'monthly' => 2000,
            'timezone' => 'Europe/Paris',
        ]);
        self::assertResponseIsSuccessful();
        $configured = $this->decode($client);
        self::assertSame(100, $configured['config']['daily']);
        self::assertSame(500, $configured['config']['weekly']);
        self::assertSame(2000, $configured['config']['monthly']);
        self::assertSame('Europe/Paris', $configured['config']['timezone']);
        self::assertNotNull($configured['config']['startedAt']);

        $job = (new JobOffer())->fill([
            'source' => 'Goal test',
            'title' => 'Développeur Symfony objectif',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'description' => 'Offre utilisée pour le test des objectifs.',
        ]);
        $application = new Application($job);
        $em->persist($job);
        $em->persist($application);
        $em->flush();

        $timeline->record(
            $job,
            JobTimelineEventType::APPLICATION_SUBMITTED,
            [],
            $application,
            new \DateTimeImmutable(),
            'goal-test',
        );
        $em->flush();

        $client->request('GET', '/api/application-goals');
        self::assertResponseIsSuccessful();
        $snapshot = $this->decode($client);
        self::assertGreaterThanOrEqual(1, $snapshot['periods']['daily']['achieved']);
        self::assertGreaterThanOrEqual(1, $snapshot['periods']['weekly']['achieved']);
        self::assertGreaterThanOrEqual(1, $snapshot['periods']['monthly']['achieved']);
        self::assertSame(100, $snapshot['periods']['daily']['target']);
        self::assertTrue($snapshot['periods']['daily']['enabled']);
    }

    public function testInvalidGoalConfigurationIsRejected(): void
    {
        $client = static::createClient();

        $client->jsonRequest('PUT', '/api/application-goals', [
            'daily' => 101,
            'weekly' => 5,
            'monthly' => 20,
            'timezone' => 'Europe/Paris',
        ]);
        self::assertResponseStatusCodeSame(400);

        $client->jsonRequest('PUT', '/api/application-goals', [
            'daily' => 5,
            'weekly' => 20,
            'monthly' => 80,
            'timezone' => 'Mars/Olympus_Mons',
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    /** @return array<string, mixed> */
    private function decode($client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
