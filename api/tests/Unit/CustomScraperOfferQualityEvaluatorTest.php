<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Application\CustomScraperOfferQualityEvaluator;
use PHPUnit\Framework\TestCase;

final class CustomScraperOfferQualityEvaluatorTest extends TestCase
{
    public function testAcceptsGroundedStructuredOfferWithUsefulDescription(): void
    {
        $quality = $this->evaluator()->evaluate([
            'title' => 'Senior Symfony Developer',
            'sourceUrl' => 'https://jobs.example.test/jobs/42',
            'company' => 'Acme France',
            'location' => 'Paris',
            'contractType' => 'Freelance',
            'workMode' => 'Hybride',
            'description' => str_repeat('Mission Symfony API Platform PostgreSQL avec une équipe produit. ', 3),
            'tjmMin' => 450,
            'tjmMax' => 500,
            'rawData' => [
                'extractionMethod' => 'JOB_LINK',
                'detailEnriched' => true,
                'detailExtractionMethod' => 'JSON_LD',
            ],
        ], 'jobs.example.test');

        self::assertTrue($quality['reliable']);
        self::assertGreaterThanOrEqual(70, $quality['score']);
        self::assertStringContainsString('JobPosting', implode(' ', $quality['reasons']));
    }

    public function testRejectsBareJobLinkWithoutDescription(): void
    {
        $quality = $this->evaluator()->evaluate([
            'title' => 'Symfony Developer',
            'sourceUrl' => 'https://jobs.example.test/jobs/42',
            'company' => 'Example Jobs',
            'description' => '',
            'rawData' => [
                'extractionMethod' => 'JOB_LINK',
                'needsDetailFetch' => true,
            ],
        ], 'jobs.example.test');

        self::assertFalse($quality['reliable']);
        self::assertLessThan(70, $quality['score']);
        self::assertStringContainsString('Description trop courte', implode(' ', $quality['reasons']));
    }

    public function testRejectsOffDomainUrlEvenWithRichContent(): void
    {
        $quality = $this->evaluator()->evaluate([
            'title' => 'Senior PHP Developer',
            'sourceUrl' => 'https://other.example.net/jobs/42',
            'company' => 'Acme',
            'description' => str_repeat('Description complète de la mission Symfony et de son contexte. ', 5),
            'rawData' => ['extractionMethod' => 'JSON_LD'],
        ], 'jobs.example.test');

        self::assertFalse($quality['reliable']);
        self::assertStringContainsString('hors du domaine', implode(' ', $quality['reasons']));
    }

    public function testRejectsGenericTitles(): void
    {
        $quality = $this->evaluator()->evaluate([
            'title' => 'Voir le poste',
            'sourceUrl' => 'https://jobs.example.test/jobs/42',
            'description' => str_repeat('Description complète. ', 10),
            'rawData' => ['extractionMethod' => 'JSON_LD'],
        ], 'jobs.example.test');

        self::assertFalse($quality['reliable']);
        self::assertSame(0, $quality['score']);
    }

    public function testRejectsKickloxLikeTitleContaminatedByCardMetadata(): void
    {
        $quality = $this->evaluator()->evaluate([
            'title' => 'Kicklox 918 Offre publiée il y a 7 jours Développeur PHP Symfony H/F CDI Paris 75005 France',
            'sourceUrl' => 'https://jobs.example.test/jobs/918',
            'description' => str_repeat('Développeur PHP Symfony pour une mission produit. ', 6),
            'rawData' => [
                'extractionMethod' => 'JOB_LINK',
                'detailEnriched' => true,
                'detailExtractionMethod' => 'DOM',
            ],
        ], 'jobs.example.test');

        self::assertFalse($quality['reliable']);
        self::assertSame(0, $quality['score']);
        self::assertStringContainsString('contaminé', implode(' ', $quality['reasons']));
    }

    public function testRejectsLongDomShellWhenItDoesNotContainDistinctiveTitleTechnology(): void
    {
        $quality = $this->evaluator()->evaluate([
            'title' => 'Développeur PHP Symfony H/F',
            'sourceUrl' => 'https://jobs.example.test/jobs/918',
            'company' => 'Example Jobs',
            'description' => str_repeat(
                'Missions Entreprises Candidats Freelances Ressources Contactez-nous Déposer une offre Retour vers les offres. ',
                5,
            ),
            'rawData' => [
                'extractionMethod' => 'JOB_LINK',
                'detailEnriched' => true,
                'detailExtractionMethod' => 'DOM',
            ],
        ], 'jobs.example.test');

        self::assertFalse($quality['reliable']);
        self::assertLessThan(70, $quality['score']);
        self::assertStringContainsString('aucun terme distinctif du titre', implode(' ', $quality['reasons']));
    }

    public function testAcceptsUnstructuredDetailWhenItContainsDistinctiveTitleTechnology(): void
    {
        $quality = $this->evaluator()->evaluate([
            'title' => 'Développeur PHP Symfony H/F',
            'sourceUrl' => 'https://jobs.example.test/jobs/918',
            'company' => 'Example Jobs',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'description' => str_repeat(
                'Nous recherchons un développeur pour maintenir une application Symfony et développer des API PHP. ',
                4,
            ),
            'rawData' => [
                'extractionMethod' => 'JOB_LINK',
                'detailEnriched' => true,
                'detailExtractionMethod' => 'DOM',
            ],
        ], 'jobs.example.test');

        self::assertTrue($quality['reliable']);
        self::assertGreaterThanOrEqual(70, $quality['score']);
    }

    public function testDoesNotRequireSemanticOverlapForPurelyGenericRoleTitles(): void
    {
        $quality = $this->evaluator()->evaluate([
            'title' => 'Senior Backend Engineer',
            'sourceUrl' => 'https://jobs.example.test/jobs/77',
            'company' => 'Example Jobs',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'description' => str_repeat('Conception de services distribués, qualité logicielle et travail en équipe produit. ', 4),
            'rawData' => [
                'extractionMethod' => 'JOB_LINK',
                'detailEnriched' => true,
                'detailExtractionMethod' => 'DOM',
            ],
        ], 'jobs.example.test');

        self::assertTrue($quality['reliable']);
    }

    private function evaluator(): CustomScraperOfferQualityEvaluator
    {
        return new CustomScraperOfferQualityEvaluator();
    }
}
