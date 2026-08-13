<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\PreferenceSignal;
use App\Service\PreferenceSignalRecorder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PreferenceSignalRecorderTest extends TestCase
{
    public function testUserRejectingAJobCreatesAnExplicitNegativePreference(): void
    {
        $application = $this->applicationWithStatus('IGNORED_NOT_MATCH');
        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted = $entity;
            });

        $signal = (new PreferenceSignalRecorder($em))->recordApplicationStatusTransition($application, 'READY_TO_SUBMIT');

        self::assertInstanceOf(PreferenceSignal::class, $signal);
        self::assertSame($signal, $persisted);
        self::assertSame('JOB_REJECTED_BY_USER', $signal->getSignalType());
        self::assertSame(-1, $signal->getPreferenceValue());
        self::assertSame(PreferenceSignal::ORIGIN_USER_DECISION, $signal->getOrigin());
    }

    public function testSubmittingAnApplicationCreatesAPositiveUserDecision(): void
    {
        $application = $this->applicationWithStatus('SUBMITTED');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');

        $signal = (new PreferenceSignalRecorder($em))->recordApplicationStatusTransition($application, 'READY_TO_SUBMIT');

        self::assertNotNull($signal);
        self::assertSame('APPLICATION_SUBMITTED', $signal->getSignalType());
        self::assertSame(1, $signal->getPreferenceValue());
        self::assertSame(PreferenceSignal::ORIGIN_USER_DECISION, $signal->getOrigin());
    }

    public function testRecruiterRejectionIsStoredAsOutcomeButNeverAsNegativePreference(): void
    {
        $application = $this->applicationWithStatus('REJECTED');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');

        $signal = (new PreferenceSignalRecorder($em))->recordApplicationStatusTransition($application, 'SUBMITTED');

        self::assertNotNull($signal);
        self::assertSame('RECRUITER_REJECTION', $signal->getSignalType());
        self::assertSame(0, $signal->getPreferenceValue());
        self::assertSame(PreferenceSignal::ORIGIN_PIPELINE_OUTCOME, $signal->getOrigin());
    }

    public function testUnchangedOrIrrelevantStatusesDoNotCreateNoise(): void
    {
        $application = $this->applicationWithStatus('READY_TO_SUBMIT');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $recorder = new PreferenceSignalRecorder($em);

        self::assertNull($recorder->recordApplicationStatusTransition($application, 'READY_TO_SUBMIT'));

        $application->fill(['status' => 'MISSING_CV']);
        self::assertNull($recorder->recordApplicationStatusTransition($application, 'READY_TO_SUBMIT'));
    }

    private function applicationWithStatus(string $status): Application
    {
        $job = (new JobOffer())->fill([
            'title' => 'Senior Symfony Developer',
            'company' => 'Example',
        ]);
        $application = new Application($job);
        $application->fill(['status' => $status]);

        return $application;
    }
}
