<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Application;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApplicationGoalApiTest extends WebTestCase
{
    public function testGoalsCountSentApplicationButNotAlreadyAppliedDecision(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

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

        $client->request('GET', '/api/application-goals');
        self::assertResponseIsSuccessful();
        $baseline = $this->decode($client);

        $job = (new JobOffer())->fill([
            'source' => 'Goal test',
            'title' => 'Développeur Symfony objectif',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'description' => 'Offre utilisée pour le test des objectifs.',
        ]);
        $sentApplication = new Application($job);
        $alreadyAppliedApplication = new Application($job);
        $em->persist($job);
        $em->persist($sentApplication);
        $em->persist($alreadyAppliedApplication);
        $em->flush();
        self::assertNotNull($sentApplication->getId());
        self::assertNotNull($alreadyAppliedApplication->getId());

        $client->jsonRequest('PATCH', sprintf('/api/applications/%d', $sentApplication->getId()), [
            'status' => 'SUBMITTED',
        ]);
        self::assertResponseIsSuccessful();

        // This is the exact persisted distinction used by the Review Queue's "Déjà postulé" action.
        $client->jsonRequest('PATCH', sprintf('/api/applications/%d', $alreadyAppliedApplication->getId()), [
            'status' => 'SUBMITTED',
            'channel' => 'Candidature externe',
        ]);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/application-goals');
        self::assertResponseIsSuccessful();
        $snapshot = $this->decode($client);

        self::assertSame(
            $baseline['periods']['daily']['achieved'] + 1,
            $snapshot['periods']['daily']['achieved'],
        );
        self::assertSame(
            $baseline['periods']['weekly']['achieved'] + 1,
            $snapshot['periods']['weekly']['achieved'],
        );
        self::assertSame(
            $baseline['periods']['monthly']['achieved'] + 1,
            $snapshot['periods']['monthly']['achieved'],
        );
        self::assertSame(100, $snapshot['periods']['daily']['target']);
        self::assertTrue($snapshot['periods']['daily']['enabled']);
    }

    public function testUndoneSubmissionDoesNotRemainInGoalProgress(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $client->jsonRequest('PUT', '/api/application-goals', [
            'daily' => 100,
            'weekly' => 500,
            'monthly' => 2000,
            'timezone' => 'Europe/Paris',
        ]);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/application-goals');
        self::assertResponseIsSuccessful();
        $baseline = $this->decode($client);

        $job = (new JobOffer())->fill([
            'source' => 'Goal undo test',
            'title' => 'Développeur Symfony undo',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'description' => 'Offre utilisée pour le test d’annulation.',
        ]);
        $application = new Application($job);
        $em->persist($job);
        $em->persist($application);
        $em->flush();
        self::assertNotNull($application->getId());

        $client->jsonRequest('PATCH', sprintf('/api/applications/%d', $application->getId()), [
            'status' => 'SUBMITTED',
        ]);
        self::assertResponseIsSuccessful();

        $client->request('POST', sprintf('/api/applications/%d/review-decision/undo', $application->getId()));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/application-goals');
        self::assertResponseIsSuccessful();
        $snapshot = $this->decode($client);
        self::assertSame($baseline['periods']['daily']['achieved'], $snapshot['periods']['daily']['achieved']);
        self::assertSame($baseline['periods']['weekly']['achieved'], $snapshot['periods']['weekly']['achieved']);
        self::assertSame($baseline['periods']['monthly']['achieved'], $snapshot['periods']['monthly']['achieved']);
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
