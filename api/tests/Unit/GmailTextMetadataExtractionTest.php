<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Messaging\Application\GmailJobAlertExtractor;
use PHPUnit\Framework\TestCase;

final class GmailTextMetadataExtractionTest extends TestCase
{
    public function testMetadataIsExtractedOnlyFromEachOfferLocalContext(): void
    {
        $html = <<<'HTML'
<section>
  <article>
    <h2>Backend Java chez Alpha</h2>
    <p>CDI · présentiel sur site · Paris · Java 21 Spring Boot Kafka.</p>
    <a href="https://www.hellowork.com/fr-fr/emplois/111.html">Backend Java chez Alpha</a>
  </article>
  <article>
    <h2>Développeur PHP Symfony chez Beta</h2>
    <p>Mission freelance · 100% remote · TJM : 550-650 € · PHP 8.3 Symfony 7 API Platform.</p>
    <a href="https://www.hellowork.com/fr-fr/emplois/222.html">Développeur PHP Symfony chez Beta</a>
  </article>
</section>
HTML;

        $offers = (new GmailJobAlertExtractor())->extract(
            'gmail-metadata-multi',
            'JOB_ALERT',
            'Offres backend Java et PHP',
            'Hellowork <alerts@example.com>',
            'CDI Java puis mission freelance PHP Symfony. TJM 550-650 €.',
            $html,
            new \DateTimeImmutable('2026-08-10T19:00:00+02:00'),
        );

        self::assertCount(2, $offers);

        self::assertSame('CDI', $offers[0]['contractType']);
        self::assertSame('Sur site', $offers[0]['workMode']);
        self::assertNull($offers[0]['tjmMin']);
        self::assertNull($offers[0]['tjmMax']);
        self::assertStringNotContainsString('freelance', mb_strtolower($offers[0]['description']));
        self::assertStringNotContainsString('550', $offers[0]['description']);

        self::assertSame('Freelance', $offers[1]['contractType']);
        self::assertSame('Télétravail', $offers[1]['workMode']);
        self::assertSame(550, $offers[1]['tjmMin']);
        self::assertSame(650, $offers[1]['tjmMax']);
        self::assertTrue($offers[1]['rawData']['textMetadataExtracted']);
    }

    public function testSingleOfferCanExtractMetadataFromMessageBodyFallback(): void
    {
        $offers = (new GmailJobAlertExtractor())->extract(
            'gmail-metadata-single',
            'JOB_ALERT',
            'Mission Symfony',
            'APEC <alerts@example.com>',
            'Mission freelance hybride avec 2 jours de télétravail. TJM : 480 €.',
            '<a href="https://www.apec.fr/candidat/recherche-emploi.html/emploi/detail-offre/999">Développeur Symfony chez Example</a>',
            new \DateTimeImmutable('2026-08-10T19:00:00+02:00'),
        );

        self::assertCount(1, $offers);
        self::assertSame('Freelance', $offers[0]['contractType']);
        self::assertSame('Hybride', $offers[0]['workMode']);
        self::assertSame(480, $offers[0]['tjmMin']);
        self::assertSame(480, $offers[0]['tjmMax']);
        self::assertSame('MESSAGE_BODY', $offers[0]['rawData']['descriptionScope']);
    }
}
