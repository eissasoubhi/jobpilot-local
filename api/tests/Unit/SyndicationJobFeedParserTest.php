<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Infrastructure\Feed\SyndicationJobFeedParser;
use PHPUnit\Framework\TestCase;

final class SyndicationJobFeedParserTest extends TestCase
{
    public function testRssOffersAreNormalizedWithCompensation(): void
    {
        $offers = (new SyndicationJobFeedParser())->parse(
            $this->fixture('symfony-jobs.rss'),
            'Symfony Jobs',
        );

        self::assertCount(2, $offers);

        self::assertSame('symfony-job-123', $offers[0]['externalId']);
        self::assertSame('Senior Symfony Developer', $offers[0]['title']);
        self::assertSame('Example Studio', $offers[0]['company']);
        self::assertSame('Freelance', $offers[0]['contractType']);
        self::assertSame('Télétravail', $offers[0]['workMode']);
        self::assertSame('Télétravail', $offers[0]['location']);
        self::assertSame(500, $offers[0]['tjmMin']);
        self::assertSame(600, $offers[0]['tjmMax']);
        self::assertSame('en', $offers[0]['language']);

        self::assertSame('Développeur PHP Symfony', $offers[1]['title']);
        self::assertSame('Société Exemple', $offers[1]['company']);
        self::assertSame('CDI', $offers[1]['contractType']);
        self::assertSame('Hybride', $offers[1]['workMode']);
        self::assertSame('Paris, France', $offers[1]['location']);
        self::assertSame(55000, $offers[1]['salaryMin']);
        self::assertSame(65000, $offers[1]['salaryMax']);
        self::assertSame('fr', $offers[1]['language']);
    }

    public function testAtomOffersAreSupported(): void
    {
        $offers = (new SyndicationJobFeedParser())->parse(
            $this->fixture('jobs.atom'),
            'Example Jobs',
        );

        self::assertCount(1, $offers);
        self::assertSame('tag:jobs.example.test,2026:42', $offers[0]['externalId']);
        self::assertSame('Backend Symfony Engineer', $offers[0]['title']);
        self::assertSame('Atom Company', $offers[0]['company']);
        self::assertSame('CDI', $offers[0]['contractType']);
        self::assertSame('Hybride', $offers[0]['workMode']);
        self::assertSame('Lyon, France', $offers[0]['location']);
        self::assertSame('https://jobs.example.test/42', $offers[0]['sourceUrl']);
    }

    public function testInvalidXmlIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('flux d’offres est invalide');

        (new SyndicationJobFeedParser())->parse('<rss><channel>', 'Broken Feed');
    }

    private function fixture(string $name): string
    {
        $content = file_get_contents(dirname(__DIR__).'/Fixtures/feeds/'.$name);
        self::assertIsString($content);

        return $content;
    }
}
