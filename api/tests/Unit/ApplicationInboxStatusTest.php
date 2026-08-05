<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Application;
use App\Entity\JobOffer;
use PHPUnit\Framework\TestCase;

final class ApplicationInboxStatusTest extends TestCase
{
    public function testInterviewAndRejectionMessagesUpdateTheApplication(): void
    {
        $application = $this->application();

        self::assertTrue($application->applyInboxCategory('INTERVIEW_REQUEST'));
        self::assertSame('INTERVIEW', $application->getStatus());
        self::assertNotNull($application->getSubmittedAt());

        self::assertTrue($application->applyInboxCategory('REJECTION'));
        self::assertSame('REJECTED', $application->getStatus());
        self::assertFalse($application->applyInboxCategory('REJECTION'));
    }

    public function testAConfirmationDoesNotDowngradeAnInterview(): void
    {
        $application = $this->application();
        $application->applyInboxCategory('INTERVIEW_REQUEST');

        self::assertFalse($application->applyInboxCategory('APPLICATION_CONFIRMATION'));
        self::assertSame('INTERVIEW', $application->getStatus());
    }

    public function testUnknownMessagesDoNotChangeTheApplication(): void
    {
        $application = $this->application();

        self::assertFalse($application->applyInboxCategory('UNKNOWN'));
        self::assertSame('DRAFT', $application->getStatus());
    }

    private function application(): Application
    {
        $job = (new JobOffer())->fill([
            'source' => 'Test',
            'title' => 'Senior Symfony Developer',
            'company' => 'Example',
            'description' => 'Mission Symfony et API Platform.',
        ]);

        return new Application($job);
    }
}
