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

    private function evaluator(): CustomScraperOfferQualityEvaluator
    {
        return new CustomScraperOfferQualityEvaluator();
    }
}
