<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Messaging\Application\PlainTextJobAlertLinkExtractor;
use PHPUnit\Framework\TestCase;

final class PlainTextJobAlertLinkExtractorTest extends TestCase
{
    public function testExtractsTitleAndLocalContextBeforeEachUrl(): void
    {
        $body = <<<'TEXT'
Backend Java chez Alpha
Paris · CDI · Java 21 Spring Boot Kafka
https://www.hellowork.com/fr-fr/emplois/111.html

Développeur PHP Symfony chez Beta
Lyon · CDI · PHP 8.3 Symfony 7 API Platform
https://www.hellowork.com/fr-fr/emplois/222.html
TEXT;

        $links = (new PlainTextJobAlertLinkExtractor())->extract($body);

        self::assertCount(2, $links);
        self::assertSame('Backend Java chez Alpha', $links[0]['label']);
        self::assertStringContainsString('Java 21', $links[0]['context']);
        self::assertStringNotContainsString('Symfony', $links[0]['context']);
        self::assertSame('Développeur PHP Symfony chez Beta', $links[1]['label']);
        self::assertStringContainsString('Symfony 7', $links[1]['context']);
        self::assertStringNotContainsString('Spring Boot', $links[1]['context']);
    }

    public function testExtractsTitleWhenUrlSharesTheSameLine(): void
    {
        $body = 'Développeur Symfony chez Acme — Paris — https://lesjeudis.com/fr/job/developpeur-symfony-123';

        $links = (new PlainTextJobAlertLinkExtractor())->extract($body);

        self::assertCount(1, $links);
        self::assertSame('Développeur Symfony chez Acme', $links[0]['label']);
        self::assertStringContainsString('Paris', $links[0]['context']);
    }

    public function testDoesNotBorrowContextAcrossBlankLinesOrOtherUrls(): void
    {
        $body = <<<'TEXT'
Développeur Symfony chez Old
https://lesjeudis.com/fr/job/old-123

https://lesjeudis.com/fr/job/no-title-456
TEXT;

        $links = (new PlainTextJobAlertLinkExtractor())->extract($body);

        self::assertCount(2, $links);
        self::assertSame('Développeur Symfony chez Old', $links[0]['label']);
        self::assertSame('', $links[1]['label']);
        self::assertSame('', $links[1]['context']);
    }
}
