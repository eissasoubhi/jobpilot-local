<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobDetailExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobListingExtractor;
use PHPUnit\Framework\TestCase;

final class GenericJobDetailExtractorTest extends TestCase
{
    public function testDomFallbackEnrichesWithoutChangingStableExternalId(): void
    {
        $candidate = [
            'source' => 'Example Jobs',
            'sourceUrl' => 'https://jobs.example.test/offres/42',
            'externalId' => 'link-stable-id',
            'title' => 'Développeur PHP',
            'company' => 'Example Jobs',
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
            ],
        ];
        $html = <<<'HTML'
<html><body>
<header>Navigation</header>
<main>
<h1>Développeur PHP Symfony Senior</h1>
<time datetime="2026-08-10">Publié aujourd’hui</time>
<p>Mission freelance hybride sur une application Symfony.</p>
<p>TJM : 430 - 480 €</p>
</main>
<footer>Mentions légales</footer>
</body></html>
HTML;

        $enriched = $this->extractor()->enrich(
            $html,
            $candidate,
            'https://jobs.example.test/offres/42',
            'Example Jobs',
        );

        self::assertSame('link-stable-id', $enriched['externalId']);
        self::assertSame('Développeur PHP Symfony Senior', $enriched['title']);
        self::assertSame('Freelance', $enriched['contractType']);
        self::assertSame('Hybride', $enriched['workMode']);
        self::assertSame(430, $enriched['tjmMin']);
        self::assertSame(480, $enriched['tjmMax']);
        self::assertStringContainsString('application Symfony', $enriched['description']);
        self::assertStringNotContainsString('Mentions légales', $enriched['description']);
        self::assertSame('DOM', $enriched['rawData']['detailExtractionMethod']);
        self::assertFalse($enriched['rawData']['needsDetailFetch']);
        self::assertTrue($enriched['rawData']['detailEnriched']);
    }

    public function testStructuredDetailWinsOverDomFallback(): void
    {
        $candidate = [
            'source' => 'Example Jobs',
            'sourceUrl' => 'https://jobs.example.test/jobs/42',
            'externalId' => 'link-stable-id',
            'title' => 'Symfony Developer',
            'company' => 'Example Jobs',
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
            'rawData' => ['extractionMethod' => 'JOB_LINK', 'needsDetailFetch' => true],
        ];
        $html = <<<'HTML'
<html><body>
<h1>Titre DOM imprécis</h1>
<script type="application/ld+json">{
  "@type":"JobPosting",
  "title":"Symfony Developer",
  "url":"https://jobs.example.test/jobs/42",
  "hiringOrganization":{"name":"Structured Company"},
  "description":"Description structurée fiable",
  "employmentType":"CDI"
}</script>
</body></html>
HTML;

        $enriched = $this->extractor()->enrich(
            $html,
            $candidate,
            'https://jobs.example.test/jobs/42',
            'Example Jobs',
        );

        self::assertSame('link-stable-id', $enriched['externalId']);
        self::assertSame('Symfony Developer', $enriched['title']);
        self::assertSame('Structured Company', $enriched['company']);
        self::assertSame('Description structurée fiable', $enriched['description']);
        self::assertSame('CDI', $enriched['contractType']);
        self::assertSame('JSON_LD', $enriched['rawData']['detailExtractionMethod']);
    }

    private function extractor(): GenericJobDetailExtractor
    {
        return new GenericJobDetailExtractor(new GenericJobListingExtractor());
    }
}
