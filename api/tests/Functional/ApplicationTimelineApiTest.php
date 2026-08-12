<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\JobTimelineEvent;
use App\Timeline\JobTimelineEventType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApplicationTimelineApiTest extends WebTestCase
{
    public function testManualSubmissionTransitionCreatesOneTimelineEvent(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $job = (new JobOffer())->fill([
            'source' => 'Test',
            'title' => 'Développeur Symfony',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'description' => 'Mission Symfony de test.',
        ]);
        $application = new Application($job);
        $em->persist($job);
        $em->persist($application);
        $em->flush();

        $applicationId = $application->getId();
        self::assertIsInt($applicationId);

        $client->jsonRequest('PATCH', '/api/applications/'.$applicationId, [
            'status' => 'SUBMITTED',
        ]);
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('SUBMITTED', $payload['status']);
        self::assertNotNull($payload['submittedAt']);

        $events = $em->getRepository(JobTimelineEvent::class)->findBy([
            'application' => $application,
            'type' => JobTimelineEventType::APPLICATION_SUBMITTED,
        ]);
        self::assertCount(1, $events);
        self::assertSame('manual-status', $events[0]->getSource());
        self::assertSame('DRAFT', $events[0]->getPayload()['previousStatus'] ?? null);
        self::assertSame($application->getSubmittedAt()?->getTimestamp(), $events[0]->getOccurredAt()->getTimestamp());

        $client->jsonRequest('PATCH', '/api/applications/'.$applicationId, [
            'status' => 'SUBMITTED',
            'message' => 'Mise à jour sans nouvelle transition.',
        ]);
        self::assertResponseIsSuccessful();

        $eventsAfterRepeatedPatch = $em->getRepository(JobTimelineEvent::class)->findBy([
            'application' => $application,
            'type' => JobTimelineEventType::APPLICATION_SUBMITTED,
        ]);
        self::assertCount(1, $eventsAfterRepeatedPatch);
    }
}
