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

    public function testKeepsNormalSingleOfferDescriptionClean(): void
    {
        $text = 'Nous cherchons un Lead PHP Symfony pour concevoir des API, accompagner l’équipe et travailler avec RabbitMQ. '
            .'Mission hybride à Paris, TJM 500 à 550 €.';

        self::assertFalse((new JobDescriptionContaminationDetector())->isMultiOfferDigest($text));
    }
}
