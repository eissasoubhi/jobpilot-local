<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Service\ReviewDecisionUndoService;
use PHPUnit\Framework\TestCase;

final class ReviewDecisionUndoServiceTest extends TestCase
{
    private ReviewDecisionUndoService $service;

    protected function setUp(): void
    {
        $this->service = new ReviewDecisionUndoService();
    }

    public function testUndoIgnoredDecisionRestoresReadyState(): void
    {
        $application = $this->application('IGNORED_NOT_MATCH');

        $previous = $this->service->undo($application);

        self::assertSame('IGNORED_NOT_MATCH', $previous);
        self::assertSame('READY_TO_SUBMIT', $application->getStatus());
        self::assertNull($application->getSubmittedAt());
    }

    public function testUndoUnavailableRestoresPreparedJobAndReadyApplication(): void
    {
        $application = $this->application('OFFER_UNAVAILABLE');
        $application->getJobOffer()->fill(['status' => 'UNAVAILABLE']);

        $previous = $this->service->undo($application);

        self::assertSame('OFFER_UNAVAILABLE', $previous);
        self::assertSame('READY_TO_SUBMIT', $application->getStatus());
        self::assertSame('PREPARED', $application->getJobOffer()->getStatus());
    }

    public function testUndoManualSubmittedDecisionClearsSubmittedAt(): void
    {
        $application = $this->application('SUBMITTED');
        self::assertNotNull($application->getSubmittedAt());

        $previous = $this->service->undo($application);

        self::assertSame('SUBMITTED', $previous);
        self::assertSame('READY_TO_SUBMIT', $application->getStatus());
        self::assertNull($application->getSubmittedAt());
        self::assertSame('Préparation locale', $application->toArray()['channel']);
    }

    public function testDoesNotPretendToUndoRealAutomaticGmailSubmission(): void
    {
        $job = $this->job();
        $application = new Application($job);
        $application->markSubmittedAutomatically('gmail-message-42');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('réellement envoyée par Gmail');

        $this->service->undo($application);
    }

    public function testRejectsUnrelatedWorkflowStatus(): void
    {
        $application = $this->application('INTERVIEW');

        $this->expectException(\LogicException::class);
        $this->service->undo($application);
    }

    private function application(string $status): Application
    {
        return (new Application($this->job()))->fill(['status' => $status]);
    }

    private function job(): JobOffer
    {
        return (new JobOffer())->fill([
            'title' => 'Développeur PHP Symfony',
            'description' => 'PHP Symfony API',
            'status' => 'PREPARED',
        ]);
    }
}
