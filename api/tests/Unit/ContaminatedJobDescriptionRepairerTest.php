<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\JobOffer;
use App\JobCatalog\Application\ContaminatedJobDescriptionRepairer;
use PHPUnit\Framework\TestCase;

final class ContaminatedJobDescriptionRepairerTest extends TestCase
{
    public function testRepairsExistingGmailDigestWithCleanCandidateDescription(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Lead Développeur PHP Symfony (H-F)',
            'description' => "Offres d'emploi similaires à Développeur PHP. Lead PHP. Voir l'offre d'emploi. Tech Lead Java. Voir l'offre d'emploi.",
        ]);

        $repaired = (new ContaminatedJobDescriptionRepairer())->repair(
            $job,
            ['description' => 'Lead Développeur PHP Symfony (H-F)'],
            'gmail',
        );

        self::assertTrue($repaired);
        self::assertSame('Lead Développeur PHP Symfony (H-F)', $job->getDescription());
    }

    public function testDoesNotOverwriteNormalExistingDescription(): void
    {
        $original = 'Conception d’API Symfony, architecture hexagonale, RabbitMQ et accompagnement technique de l’équipe.';
        $job = (new JobOffer())->fill([
            'title' => 'Lead PHP Symfony',
            'description' => $original,
        ]);

        $repaired = (new ContaminatedJobDescriptionRepairer())->repair(
            $job,
            ['description' => 'Une autre description plus courte.'],
            'gmail',
        );

        self::assertFalse($repaired);
        self::assertSame($original, $job->getDescription());
    }

    public function testDoesNotApplyGmailRepairPolicyToOtherConnectors(): void
    {
        $original = "Offres d'emploi similaires à PHP. Voir l'offre d'emploi. Voir l'offre d'emploi.";
        $job = (new JobOffer())->fill([
            'title' => 'Lead PHP Symfony',
            'description' => $original,
        ]);

        $repaired = (new ContaminatedJobDescriptionRepairer())->repair(
            $job,
            ['description' => 'Lead PHP Symfony'],
            'adzuna',
        );

        self::assertFalse($repaired);
        self::assertSame($original, $job->getDescription());
    }

    public function testRejectsAnotherDigestAsRepairCandidate(): void
    {
        $original = "Offres d'emploi similaires à PHP. Voir l'offre d'emploi. Voir l'offre d'emploi.";
        $job = (new JobOffer())->fill([
            'title' => 'Lead PHP Symfony',
            'description' => $original,
        ]);

        $repaired = (new ContaminatedJobDescriptionRepairer())->repair(
            $job,
            ['description' => "Recommended jobs. Voir l'offre d'emploi. Voir l'offre d'emploi."],
            'gmail',
        );

        self::assertFalse($repaired);
        self::assertSame($original, $job->getDescription());
    }
}
