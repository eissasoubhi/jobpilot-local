<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\JobTimelineEvent;
use App\Timeline\JobTimelineEventType;
use App\Timeline\JobTimelineRecorder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class JobTimelineEventTest extends TestCase
{
    public function testEventKeepsBusinessContextWithoutMutableSetters(): void
    {
        $job = (new JobOffer())->fill(['title' => 'Développeur Symfony', 'company' => 'Acme']);
        $application = new Application($job);
        $occurredAt = new \DateTimeImmutable('2026-08-12T00:10:00+02:00');

        $event = new JobTimelineEvent(
            $job,
            JobTimelineEventType::APPLICATION_SUBMITTED,
            ['status' => 'SUBMITTED'],
            $application,
            $occurredAt,
            'manual',
        );

        self::assertSame($job, $event->getJobOffer());
        self::assertSame($application, $event->getApplication());
        self::assertSame(JobTimelineEventType::APPLICATION_SUBMITTED, $event->getType());
        self::assertSame('manual', $event->getSource());
        self::assertSame(['status' => 'SUBMITTED'], $event->getPayload());
        self::assertSame($occurredAt, $event->getOccurredAt());
        self::assertFalse(method_exists($event, 'setType'));
        self::assertFalse(method_exists($event, 'setOccurredAt'));
    }

    public function testUnknownEventTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JobTimelineEvent(new JobOffer(), 'TECHNICAL_RETRY');
    }

    public function testApplicationMustBelongToSameJobOffer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JobTimelineEvent(
            new JobOffer(),
            JobTimelineEventType::PREPARATION_CREATED,
            [],
            new Application(new JobOffer()),
        );
    }

    public function testRecorderPersistsWithoutForcingTransactionFlush(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(JobTimelineEvent::class));
        $em->expects(self::never())->method('flush');

        $event = (new JobTimelineRecorder($em))->record(
            new JobOffer(),
            JobTimelineEventType::OFFER_IMPORTED,
            ['sourceCode' => 'manual'],
        );

        self::assertSame(JobTimelineEventType::OFFER_IMPORTED, $event->getType());
    }
}
