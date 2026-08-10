<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobListingExtractor;
use PHPUnit\Framework\TestCase;

final class GenericJobListingExtractorTest extends TestCase
{
    public function testExtractsStructuredJobPostingIntoCanonicalPreviewShape(): void
    {
        $html = <<<'HTML'
<!doctype html><html><body>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "JobPosting",
  "identifier": {"value": "PHP-42"},
  "title": "Développeur PHP Symfony Senior",
  "description": "Construire des API Symfony et maintenir la plateforme.",
  "datePosted": "2026-08-10",
  "employmentType": ["FULL_TIME", "CONTRACTOR"],
  "hiringOrganization": {"name": "Example Tech"},
  "jobLocation": {"address": {"addressLocality": "Paris", "addressCountry": "FR"}},
  "baseSalary": {"value": {"minValue": 500, "maxValue": 550, "unitText": "DAY"}},
  "url": "/jobs/php-symfony-senior"
}
</script>
</body></html>
HTML;

        $offers = (new GenericJobListingExtractor())->extract($html, 'https://jobs.example.com/jobs', 'Example Jobs');

        self::assertCount(1, $offers);
        self::assertSame('PHP-42', $offers[0]['externalId']);
        self::assertSame('Développeur PHP Symfony Senior', $offers[0]['title']);
        self::assertSame('Example Tech', $offers[0]['company']);
        self::assertSame('Paris, FR', $offers[0]['location']);
        self::assertSame('FULL_TIME, CONTRACTOR', $offers[0]['contractType']);
        self::assertSame(500, $offers[0]['tjmMin']);
        self::assertSame(550, $offers[0]['tjmMax']);
        self::assertSame('https://jobs.example.com/jobs/php-symfony-senior', $offers[0]['sourceUrl']);
        self::assertSame('JSON_LD', $offers[0]['rawData']['extractionMethod']);
    }

    public function testFindsJobPostingInsideGraphAndDetectsRemoteWork(): void
    {
        $html = <<<'HTML'
<html><body><script type="application/ld+json">
{"@context":"https://schema.org","@graph":[
  {"@type":"Organization","name":"Acme"},
  {"@type":"JobPosting","title":"Backend Engineer","jobLocationType":"TELECOMMUTE","url":"https://jobs.example.com/positions/backend"}
]}
</script></body></html>
HTML;

        $offers = (new GenericJobListingExtractor())->extract($html, 'https://jobs.example.com/positions', 'Example Jobs');

        self::assertCount(1, $offers);
        self::assertSame('Backend Engineer', $offers[0]['title']);
        self::assertSame('Télétravail', $offers[0]['location']);
        self::assertSame('Télétravail', $offers[0]['workMode']);
    }

    public function testFallsBackToSameDomainJobLinksWithoutFollowingThem(): void
    {
        $html = <<<'HTML'
<html><body><main>
<article><h2>Développeur Symfony</h2><a href="/offres/developpeur-symfony">Voir l'offre</a></article>
<a href="/jobs/react-engineer">React Engineer</a>
<a href="https://tracking.example.net/jobs/external">External job</a>
<a href="/about">À propos</a>
</main></body></html>
HTML;

        $offers = (new GenericJobListingExtractor())->extract($html, 'https://jobs.example.com/offres', 'Example Jobs');

        self::assertCount(2, $offers);
        self::assertSame('Développeur Symfony', $offers[0]['title']);
        self::assertSame('JOB_LINK', $offers[0]['rawData']['extractionMethod']);
        self::assertTrue($offers[0]['rawData']['needsDetailFetch']);
        self::assertSame('https://jobs.example.com/offres/developpeur-symfony', $offers[0]['sourceUrl']);
        self::assertSame('React Engineer', $offers[1]['title']);
    }

    public function testStructuredDataTakesPriorityOverHeuristicLinks(): void
    {
        $html = <<<'HTML'
<html><body>
<script type="application/ld+json">{"@type":"JobPosting","title":"PHP Engineer","url":"/jobs/php"}</script>
<a href="/jobs/react">React Engineer</a>
</body></html>
HTML;

        $offers = (new GenericJobListingExtractor())->extract($html, 'https://jobs.example.com/jobs', 'Example Jobs');

        self::assertCount(1, $offers);
        self::assertSame('PHP Engineer', $offers[0]['title']);
        self::assertSame('JSON_LD', $offers[0]['rawData']['extractionMethod']);
    }
}
