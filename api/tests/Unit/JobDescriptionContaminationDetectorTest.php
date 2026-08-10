<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\JobDescriptionContaminationDetector;
use PHPUnit\Framework\TestCase;

final class JobDescriptionContaminationDetectorTest extends TestCase
{
    public function testDetectsLinkedinSimilarJobsDigestFromHeading(): void
    {
        $text = <<<'TEXT'
Offres d'emploi similaires à Développeur et Lead Dev PHP (H/F)
Lead Développeur PHP Symfony (H-F)
Actimage
Voir l'offre d'emploi
Tech Lead PHP H/F
Proelan
Voir l'offre d'emploi
TEXT;

        self::assertTrue((new JobDescriptionContaminationDetector())->isMultiOfferDigest($text));
    }

    public function testDetectsDigestFromMultipleJobUrlsEvenWithoutHeading(): void
    {
        $text = 'Lead PHP https://www.linkedin.com/comm/jobs/view/4429991557?trackingId=abc '
            .'Tech Lead Symfony https://www.linkedin.com/comm/jobs/view/4445927337?trackingId=def';

        self::assertTrue((new JobDescriptionContaminationDetector())->isMultiOfferDigest($text));
    }

    public function testDoesNotTreatTrackingVariantsOfOneJobAsMultipleOffers(): void
    {
        $text = 'Lead PHP Symfony https://www.linkedin.com/comm/jobs/view/4429991557?trackingId=abc '
            .'https://www.linkedin.com/comm/jobs/view/4429991557?trackingId=def';

        self::assertFalse((new JobDescriptionContaminationDetector())->isMultiOfferDigest($text));
    }

    public function testRecoversOnlyTheCurrentOfferBlockFromStoredDigest(): void
    {
        $text = <<<'TEXT'
Offres d'emploi similaires à Développeur et Lead Dev PHP (H/F) chez Davidson consulting
Lead Développeur PHP Symfony (H-F) Actimage Arcueil Voir l'offre d'emploi : https://www.linkedin.com/comm/jobs/view/4429991557?trackingId=abc
--------------------------------------------------
Tech Lead PHP H/F Proelan Sophia Antipolis Voir l'offre d'emploi : https://www.linkedin.com/comm/jobs/view/4445927337?trackingId=def
--------------------------------------------------
Développeur Full Stack expérimenté - PHP Symfony & Angular H/F ALCYON France
TEXT;

        $summary = (new JobDescriptionContaminationDetector())->localSummary(
            $text,
            'Lead Développeur PHP Symfony (H-F)',
        );

        self::assertSame('Lead Développeur PHP Symfony (H-F) Actimage Arcueil', $summary);
        self::assertStringNotContainsString('Tech Lead PHP H/F', $summary);
        self::assertStringNotContainsString('https://', $summary);
    }

    public function testFallsBackToTitleWhenTheCurrentOfferCannotBeLocatedInDigest(): void
    {
        $summary = (new JobDescriptionContaminationDetector())->localSummary(
            "Offres recommandées. Voir l'offre d'emploi. Voir l'offre d'emploi.",
            'Lead PHP Symfony',
        );

        self::assertSame('Lead PHP Symfony', $summary);
    }

    public function testKeepsNormalSingleOfferDescriptionClean(): void
    {
        $text = 'Nous cherchons un Lead PHP Symfony pour concevoir des API, accompagner l’équipe et travailler avec RabbitMQ. '
            .'Mission hybride à Paris, TJM 500 à 550 €.';

        $detector = new JobDescriptionContaminationDetector();
        self::assertFalse($detector->isMultiOfferDigest($text));
        self::assertSame($text, $detector->localSummary($text, 'Lead PHP Symfony'));
    }
}
