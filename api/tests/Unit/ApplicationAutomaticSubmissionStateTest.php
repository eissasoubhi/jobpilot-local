<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Application;
use App\Entity\CvDocument;
use App\Entity\JobOffer;
use PHPUnit\Framework\TestCase;

final class ApplicationAutomaticSubmissionStateTest extends TestCase
{
    public function testApplicationWithoutCvIsNotMarkedReady(): void
    {
        $application = new Application(new JobOffer());

        $application->prepare(null, 'Message', 'Lettre', null);

        self::assertSame('MISSING_CV', $application->getStatus());
        self::assertNull($application->getCvDocument());
    }

    public function testAttachingCvRepairsAnApplicationMissingItsCv(): void
    {
        $application = new Application(new JobOffer());
        $cv = $this->cv();
        $application->prepare(null, 'Message', 'Lettre', null);

        $application->attachCv($cv);

        self::assertSame('READY_TO_SUBMIT', $application->getStatus());
        self::assertSame($cv, $application->getCvDocument());
    }

    public function testPendingSubmissionCannotBePreparedAgain(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Senior Symfony Developer',
            'description' => 'Poste backend',
        ]);
        $cv = $this->cv();
        $application = new Application($job);

        $application->prepare($cv, 'Message initial', 'Lettre initiale', '500 € HT/jour');
        $application->markSubmissionAttempt();
        $application->prepare($cv, 'Message remplacé', 'Lettre remplacée', null);

        self::assertSame('SUBMISSION_PENDING', $application->getStatus());
        self::assertSame('Message initial', $application->getMessage());
        self::assertNotNull($application->getSubmissionAttemptedAt());
    }

    public function testFailedSubmissionIsRecordedWithoutPretendingItWasSent(): void
    {
        $application = new Application(new JobOffer());
        $application->prepare($this->cv(), 'Message', 'Lettre', null);
        $application->markSubmissionAttempt();
        $application->markSubmissionFailed('Gmail indisponible');

        self::assertSame('SUBMISSION_FAILED', $application->getStatus());
        self::assertSame('Gmail indisponible', $application->getSubmissionError());
        self::assertNull($application->getSubmittedAt());
        self::assertNull($application->getGmailMessageId());
    }

    public function testSuccessfulAutomaticSubmissionStoresGmailReference(): void
    {
        $application = new Application(new JobOffer());
        $application->prepare($this->cv(), 'Message', 'Lettre', null);
        $application->markSubmissionAttempt();
        $application->markSubmittedAutomatically('gmail-message-123');

        self::assertSame('SUBMITTED', $application->getStatus());
        self::assertSame('gmail-message-123', $application->getGmailMessageId());
        self::assertNotNull($application->getSubmittedAt());
        self::assertSame('gmail:gmail-message-123', $application->toArray()['confirmationRef']);
    }

    private function cv(): CvDocument
    {
        return new CvDocument('CV Symfony', 'cv.pdf', 'stored.pdf', 'fr', 'application/pdf', 1000);
    }
}
