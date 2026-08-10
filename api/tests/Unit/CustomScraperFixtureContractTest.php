<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Application\CustomScraperOfferQualityEvaluator;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericHtmlModeDetector;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobDetailExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobListingExtractor;
use PHPUnit\Framework\TestCase;

final class CustomScraperFixtureContractTest extends TestCase
{
    public function testJsonLdListingFixtureProducesCanonicalOffers(): void
    {
        $offers = (new GenericJobListingExtractor())->extract(
            $this->fixture('listing-jobposting.html'),
            'https://jobs.example.test/jobs',
            'Example Careers',
        );

        self::assertCount(2, $offers);
        self::assertSame('Développeur PHP Symfony', $offers[0]['title']);
        self::assertSame('Example Labs', $offers[0]['company']);
        self::assertSame('https://jobs.example.test/jobs/php-symfony-1001', $offers[0]['sourceUrl']);
        self::assertStringContainsString('Symfony', $offers[0]['description']);
        self::assertSame('JSON_LD', $offers[0]['rawData']['extractionMethod']);

        self::assertSame('Développeur Frontend React', $offers[1]['title']);
        self::assertSame('https://jobs.example.test/jobs/react-1002', $offers[1]['sourceUrl']);
    }

    public function testDomDetailFixtureEnrichesTheSameCanonicalCandidate(): void
    {
        $listingExtractor = new GenericJobListingExtractor();
        $detailExtractor = new GenericJobDetailExtractor($listingExtractor);
        $candidate = [
            'source' => 'Example Consulting',
            'sourceUrl' => 'https://jobs.example.test/jobs/lead-symfony',
            'externalId' => 'fixture-lead-symfony',
            'title' => 'Lead Developer PHP Symfony',
            'company' => 'Example Consulting',
            'location' => '',
            'contractType' => '',
            'workMode' => '',
            'language' => 'fr',
            'description' => '',
            'publishedAt' => null,
            'salaryMin' => null,
            'salaryMax' => null,
            'tjmMin' => null,
            'tjmMax' => null,
            'rawData' => [
                'extractionMethod' => 'JOB_LINK',
                'needsDetailFetch' => true,
                'detailEnriched' => false,
            ],
        ];

        $offer = $detailExtractor->enrich(
            $this->fixture('detail-dom.html'),
            $candidate,
            'https://jobs.example.test/jobs/lead-symfony',
            'Example Consulting',
        );

        self::assertSame('fixture-lead-symfony', $offer['externalId']);
        self::assertSame('Lead Developer PHP Symfony', $offer['title']);
        self::assertSame('Freelance', $offer['contractType']);
        self::assertSame('Hybride', $offer['workMode']);
        self::assertSame(500, $offer['tjmMin']);
        self::assertSame(600, $offer['tjmMax']);
        self::assertStringContainsString('RabbitMQ', $offer['description']);
        self::assertTrue($offer['rawData']['detailEnriched']);
        self::assertFalse($offer['rawData']['needsDetailFetch']);
        self::assertSame('DOM', $offer['rawData']['detailExtractionMethod']);
    }

    public function testJavascriptShellFixtureRequestsBrowserInsteadOfInventingOffers(): void
    {
        $html = $this->fixture('browser-js-shell.html');
        $analysis = (new GenericHtmlModeDetector())->analyze($html);
        $offers = (new GenericJobListingExtractor())->extract(
            $html,
            'https://jobs.example.test/jobs',
            'Example Careers',
        );

        self::assertSame(CustomScraperSource::MODE_BROWSER, $analysis['recommendedMode']);
        self::assertSame([], $offers);
    }

    public function testMissingDescriptionFixtureCannotPassExtractionQualityGate(): void
    {
        $offers = (new GenericJobListingExtractor())->extract(
            $this->fixture('degraded-jobposting.html'),
            'https://jobs.example.test/jobs',
            'Example Careers',
        );

        self::assertCount(1, $offers);
        self::assertSame('Développeur PHP', $offers[0]['title']);
        self::assertSame('', trim((string) ($offers[0]['description'] ?? '')));

        $quality = (new CustomScraperOfferQualityEvaluator())->evaluate($offers[0], 'jobs.example.test');
        self::assertFalse($quality['reliable']);
        self::assertContains('Description trop courte pour un import automatique.', $quality['reasons']);
    }

    private function fixture(string $name): string
    {
        $path = __DIR__.'/../Fixtures/custom-scrapers/'.$name;
        $content = file_get_contents($path);
        self::assertIsString($content, 'Fixture introuvable : '.$name);

        return $content;
    }
}
