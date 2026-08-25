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
    public function testOfferTimelineReturnsOnlyPersistedEventsInReverseChronologicalOrder(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $job = $this->job('Timeline API');
        $application = new Application($job);
        $otherJob = $this->job('Other Timeline');
        $em->persist($job);
        $em->persist($application);
        $em->persist($otherJob);
        $em->persist(new JobTimelineEvent(
            $job,
            JobTimelineEventType::APPLICATION_SUBMITTED,
            ['previousStatus' => 'DRAFT'],
            $application,
            new \DateTimeImmutable('2026-08-12T09:00:00+02:00'),
            'manual-status',
        ));
        $em->persist(new JobTimelineEvent(
            $job,
            JobTimelineEventType::INTERVIEW,
            ['category' => 'INTERVIEW_REQUEST'],
            $application,
            new \DateTimeImmutable('2026-08-13T10:30:00+02:00'),
            'gmail-inbox',
        ));
        $em->persist(new JobTimelineEvent(
            $otherJob,
            JobTimelineEventType::OFFER_IMPORTED,
            [],
            null,
            new \DateTimeImmutable('2026-08-14T11:00:00+02:00'),
            'connector',
        ));
        $em->flush();

        $jobId = $job->getId();
        $applicationId = $application->getId();
        self::assertIsInt($jobId);
        self::assertIsInt($applicationId);

        $client->request('GET', '/api/jobs/'.$jobId.'/timeline');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(2, $payload);
        self::assertSame(
            [JobTimelineEventType::INTERVIEW, JobTimelineEventType::APPLICATION_SUBMITTED],
            array_column($payload, 'type'),
        );
        self::assertSame($jobId, $payload[0]['jobOfferId']);
        self::assertSame($applicationId, $payload[0]['applicationId']);
        self::assertSame('gmail-inbox', $payload[0]['source']);
        self::assertSame(['category' => 'INTERVIEW_REQUEST'], $payload[0]['payload']);
        self::assertSame(
            (new \DateTimeImmutable('2026-08-13T10:30:00+02:00'))->getTimestamp(),
            (new \DateTimeImmutable($payload[0]['occurredAt']))->getTimestamp(),
        );
        self::assertArrayHasKey('recordedAt', $payload[0]);
    }

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

    private function job(string $company): JobOffer
    {
        return (new JobOffer())->fill([
            'source' => 'Test',
            'title' => 'Développeur Symfony',
            'company' => $company,
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'description' => 'Mission Symfony de test.',
        ]);
    }
}
