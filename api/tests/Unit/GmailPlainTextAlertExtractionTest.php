<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Messaging\Application\GmailJobAlertExtractor;
use PHPUnit\Framework\TestCase;

final class GmailPlainTextAlertExtractionTest extends TestCase
{
    public function testMultiplePlainTextOffersKeepTitlesAndDescriptionsLocal(): void
    {
        $body = <<<'TEXT'
Backend Java chez Alpha
Paris · CDI · Java 21 Spring Boot Kafka
https://www.hellowork.com/fr-fr/emplois/111.html

Développeur PHP Symfony chez Beta
Lyon · CDI · PHP 8.3 Symfony 7 API Platform
https://www.hellowork.com/fr-fr/emplois/222.html
TEXT;

        $offers = (new GmailJobAlertExtractor())->extract(
            'gmail-plain-multi',
            'JOB_ALERT',
            'Vos offres backend Java et Symfony',
            'Hellowork <alerts@example.com>',
            $body,
            '',
            new \DateTimeImmutable('2026-08-10T18:30:00+02:00'),
        );

        self::assertCount(2, $offers);
        self::assertSame('Backend Java', $offers[0]['title']);
        self::assertSame('Alpha', $offers[0]['company']);
        self::assertStringContainsString('Java 21', $offers[0]['description']);
        self::assertStringNotContainsString('Symfony', $offers[0]['description']);
        self::assertSame('LINK_CONTEXT', $offers[0]['rawData']['descriptionScope']);

        self::assertSame('Développeur PHP Symfony', $offers[1]['title']);
        self::assertSame('Beta', $offers[1]['company']);
        self::assertStringContainsString('Symfony 7', $offers[1]['description']);
        self::assertStringNotContainsString('Spring Boot', $offers[1]['description']);
        self::assertSame('LINK_CONTEXT', $offers[1]['rawData']['descriptionScope']);
    }

    public function testUntitledPlainTextLinkIsIgnoredWhenSeveralOffersExist(): void
    {
        $body = <<<'TEXT'
Backend Java chez Alpha
Java 21 Spring Boot
https://www.hellowork.com/fr-fr/emplois/111.html

https://www.hellowork.com/fr-fr/emplois/222.html
TEXT;

        $offers = (new GmailJobAlertExtractor())->extract(
            'gmail-plain-untitled',
            'JOB_ALERT',
            'Deux nouvelles offres',
            'Hellowork <alerts@example.com>',
            $body,
            '',
            new \DateTimeImmutable('2026-08-10T18:30:00+02:00'),
        );

        self::assertCount(1, $offers);
        self::assertSame('Backend Java', $offers[0]['title']);
    }
}
