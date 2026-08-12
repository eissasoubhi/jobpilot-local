<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Application;
use App\Entity\InboxMessage;
use App\Entity\JobOffer;
use App\Entity\JobTimelineEvent;
use App\Timeline\JobTimelineEventType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GmailInboxTimelineListenerTest extends WebTestCase
{
    public function testInboxTransitionsCreateBusinessEventsWithMailTimestampAndNoDuplicates(): void
    {
        static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $application = $this->persistApplication($em, 'Example Reply');
        $replyAt = new \DateTimeImmutable('2026-08-12T09:15:00+02:00');

        self::assertTrue($application->applyInboxCategory('APPLICATION_REPLY'));
        $this->persistMessage($em, $application, 'APPLICATION_REPLY', $replyAt, 'reply');
        $em->flush();

        $replyEvents = $em->getRepository(JobTimelineEvent::class)->findBy([
            'application' => $application,
            'type' => JobTimelineEventType::RESPONSE_RECEIVED,
        ]);
        self::assertCount(1, $replyEvents);
        self::assertSame('gmail-inbox', $replyEvents[0]->getSource());
        self::assertSame('APPLICATION_REPLY', $replyEvents[0]->getPayload()['category'] ?? null);
        self::assertSame('DRAFT', $replyEvents[0]->getPayload()['previousStatus'] ?? null);
        self::assertSame($replyAt->getTimestamp(), $replyEvents[0]->getOccurredAt()->getTimestamp());

        $interviewAt = $replyAt->modify('+1 day');
        self::assertTrue($application->applyInboxCategory('INTERVIEW_REQUEST'));
        $this->persistMessage($em, $application, 'INTERVIEW_REQUEST', $interviewAt, 'interview');
        $em->flush();

        $interviewEvents = $em->getRepository(JobTimelineEvent::class)->findBy([
            'application' => $application,
            'type' => JobTimelineEventType::INTERVIEW,
        ]);
        self::assertCount(1, $interviewEvents);
        self::assertSame('RESPONSE_RECEIVED', $interviewEvents[0]->getPayload()['previousStatus'] ?? null);
        self::assertSame($interviewAt->getTimestamp(), $interviewEvents[0]->getOccurredAt()->getTimestamp());

        self::assertFalse($application->applyInboxCategory('INTERVIEW_REQUEST'));
        $this->persistMessage($em, $application, 'INTERVIEW_REQUEST', $interviewAt->modify('+1 hour'), 'interview-repeat');
        $em->flush();

        self::assertCount(1, $em->getRepository(JobTimelineEvent::class)->findBy([
            'application' => $application,
            'type' => JobTimelineEventType::INTERVIEW,
        ]));
    }

    public function testRejectionAndInformationRequestUseExistingTimelineTypes(): void
    {
        static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $rejected = $this->persistApplication($em, 'Example Rejection');
        $rejectedAt = new \DateTimeImmutable('2026-08-12T11:30:00+02:00');
        self::assertTrue($rejected->applyInboxCategory('REJECTION'));
        $this->persistMessage($em, $rejected, 'REJECTION', $rejectedAt, 'rejection');
        $em->flush();

        $rejectionEvents = $em->getRepository(JobTimelineEvent::class)->findBy([
            'application' => $rejected,
            'type' => JobTimelineEventType::REJECTED,
        ]);
        self::assertCount(1, $rejectionEvents);
        self::assertSame('REJECTION', $rejectionEvents[0]->getPayload()['category'] ?? null);

        $informationRequest = $this->persistApplication($em, 'Example Information');
        $informationAt = new \DateTimeImmutable('2026-08-12T12:00:00+02:00');
        self::assertTrue($informationRequest->applyInboxCategory('INFORMATION_REQUEST'));
        $this->persistMessage($em, $informationRequest, 'INFORMATION_REQUEST', $informationAt, 'information');
        $em->flush();

        $responseEvents = $em->getRepository(JobTimelineEvent::class)->findBy([
            'application' => $informationRequest,
            'type' => JobTimelineEventType::RESPONSE_RECEIVED,
        ]);
        self::assertCount(1, $responseEvents);
        self::assertSame('INFORMATION_REQUEST', $responseEvents[0]->getPayload()['category'] ?? null);
    }

    public function testApplicationConfirmationDoesNotInventUnsupportedTimelineEvent(): void
    {
        static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $application = $this->persistApplication($em, 'Example Confirmation');
        self::assertTrue($application->applyInboxCategory('APPLICATION_CONFIRMATION'));
        $this->persistMessage(
            $em,
            $application,
            'APPLICATION_CONFIRMATION',
            new \DateTimeImmutable('2026-08-12T13:00:00+02:00'),
            'confirmation',
        );
        $em->flush();

        self::assertSame('APPLICATION_CONFIRMED', $application->getStatus());
        self::assertSame([], $em->getRepository(JobTimelineEvent::class)->findBy([
            'application' => $application,
        ]));
    }

    private function persistApplication(EntityManagerInterface $em, string $company): Application
    {
        $job = (new JobOffer())->fill([
            'source' => 'Gmail test',
            'title' => 'Développeur Symfony',
            'company' => $company,
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'description' => 'Mission Symfony de test.',
        ]);
        $application = new Application($job);
        $em->persist($job);
        $em->persist($application);
        $em->flush();

        return $application;
    }

    private function persistMessage(
        EntityManagerInterface $em,
        Application $application,
        string $category,
        \DateTimeImmutable $receivedAt,
        string $suffix,
    ): void {
        $message = (new InboxMessage('timeline-'.$suffix.'-'.bin2hex(random_bytes(4)), 'thread-'.$suffix))->fill(
            'recruiter@example.com',
            'Suivi candidature Développeur Symfony',
            'Message de test',
            $receivedAt,
            $category,
        );
        $message->associate($application);
        $em->persist($message);
    }
}
