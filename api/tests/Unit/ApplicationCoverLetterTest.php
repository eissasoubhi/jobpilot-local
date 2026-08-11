<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Application;
use App\Entity\JobOffer;
use PHPUnit\Framework\TestCase;

final class ApplicationCoverLetterTest extends TestCase
{
    public function testManualLetterSurvivesAutomaticPreparationAndCanBeReset(): void
    {
        $application = new Application($this->job());
        $application->prepare(null, 'Message initial', 'Version générée 1', null);

        self::assertSame('Version générée 1', $application->getCoverLetter());
        self::assertSame('Version générée 1', $application->getGeneratedCoverLetter());
        self::assertFalse($application->isCoverLetterManuallyEdited());

        $statusBeforeEdit = $application->getStatus();
        $application->editCoverLetter("Version manuelle\navec une seconde ligne.");

        self::assertSame($statusBeforeEdit, $application->getStatus());
        self::assertSame("Version manuelle\navec une seconde ligne.", $application->getCoverLetter());
        self::assertTrue($application->isCoverLetterManuallyEdited());
        self::assertNotNull($application->getCoverLetterEditedAt());

        $application->prepare(null, 'Message recalculé', 'Version générée 2', null);

        self::assertSame('Version générée 2', $application->getGeneratedCoverLetter());
        self::assertSame("Version manuelle\navec une seconde ligne.", $application->getCoverLetter());
        self::assertTrue($application->isCoverLetterManuallyEdited());

        $application->resetCoverLetter();

        self::assertSame('Version générée 2', $application->getCoverLetter());
        self::assertFalse($application->isCoverLetterManuallyEdited());
        self::assertNull($application->getCoverLetterEditedAt());
        self::assertFalse($application->toArray()['coverLetterManuallyEdited']);
    }

    public function testManualLetterCannotBeBlank(): void
    {
        $application = new Application($this->job());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ne peut pas être vide');
        $application->editCoverLetter(" \n ");
    }

    public function testResetRequiresGeneratedVersion(): void
    {
        $application = new Application($this->job());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('version générée');
        $application->resetCoverLetter();
    }

    private function job(): JobOffer
    {
        return (new JobOffer())->fill([
            'title' => 'Développeur PHP Symfony',
            'company' => 'Example Corp',
            'description' => 'Mission de test.',
            'language' => 'fr',
        ]);
    }
}
