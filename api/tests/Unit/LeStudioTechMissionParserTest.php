<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Infrastructure\Scraping\Html\LeStudioTechMissionParser;
use PHPUnit\Framework\TestCase;

final class LeStudioTechMissionParserTest extends TestCase
{
    public function testParsesPublicMissionCardsWithoutDependingOnCssClasses(): void
    {
        $offers = (new LeStudioTechMissionParser())->parseListing(
            $this->fixture('le-studio-tech-missions.html'),
            'https://app.lestudiotech.com/freelances/missions',
        );

        self::assertCount(2, $offers);
        self::assertSame('T-1201', $offers[0]['externalId']);
        self::assertSame('Développeur PHP Symfony', $offers[0]['title']);
        self::assertSame('Le Studio Tech', $offers[0]['company']);
        self::assertSame('Paris', $offers[0]['location']);
        self::assertSame('Freelance', $offers[0]['contractType']);
        self::assertSame('Hybride', $offers[0]['workMode']);
        self::assertSame(550, $offers[0]['tjmMin']);
        self::assertSame(550, $offers[0]['tjmMax']);
        self::assertSame('2026-08-09T00:00:00+02:00', $offers[0]['publishedAt']);
        self::assertSame(
            'https://app.lestudiotech.com/freelances/missions/11111111-1111-1111-1111-111111111111/developpeur-php-symfony',
            $offers[0]['sourceUrl'],
        );
        self::assertSame('5 ans', $offers[0]['rawData']['minimumExperience']);
        self::assertFalse($offers[0]['rawData']['detailEnriched']);

        self::assertSame('T-1202', $offers[1]['externalId']);
        self::assertSame('Sur site', $offers[1]['workMode']);
        self::assertSame(600, $offers[1]['tjmMin']);
    }

    public function testEnrichesDescriptionAndRejectsEndedMission(): void
    {
        $parser = new LeStudioTechMissionParser();
        $offer = $parser->parseListing(
            $this->fixture('le-studio-tech-missions.html'),
            'https://app.lestudiotech.com/freelances/missions',
        )[0];

        $enriched = $parser->enrichDetail($this->fixture('le-studio-tech-detail.html'), $offer);
        self::assertNotNull($enriched);
        self::assertSame('Développeur PHP Symfony Senior', $enriched['title']);
        self::assertStringContainsString('application Symfony existante', $enriched['description']);
        self::assertStringContainsString('PHP 8', $enriched['description']);
        self::assertStringNotContainsString('Informations de contact', $enriched['description']);
        self::assertTrue($enriched['rawData']['detailEnriched']);

        self::assertNull($parser->enrichDetail($this->fixture('le-studio-tech-ended.html'), $offer));
    }

    private function fixture(string $name): string
    {
        $content = file_get_contents(dirname(__DIR__).'/Fixtures/html/'.$name);
        self::assertIsString($content);

        return $content;
    }
}
