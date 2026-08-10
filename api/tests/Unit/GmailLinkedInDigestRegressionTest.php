<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Messaging\Application\GmailJobAlertExtractor;
use PHPUnit\Framework\TestCase;

final class GmailLinkedInDigestRegressionTest extends TestCase
{
    public function testSimilarJobsDigestNeverFallsBackToWholeMessageBody(): void
    {
        $plainBody = <<<'TEXT'
Offres d'emploi similaires à Développeur et Lead Dev PHP (H/F) chez Davidson consulting

Lead Développeur PHP Symfony (H-F)
Actimage
Arcueil

Tech Lead PHP H/F
Proelan - Sophia Antipolis

Tech Lead PHP / Symfony H/F
Nicholson Search and Selection
France

Développeur Full Stack expérimenté - PHP Symfony & Angular H/F
ALCYON France
TEXT;

        $htmlBody = <<<'HTML'
<html><body>
<article>
  <a href="https://www.linkedin.com/comm/jobs/view/4429991557?trackingId=abc">Lead Développeur PHP Symfony (H-F)</a>
</article>
</body></html>
HTML;

        $offers = (new GmailJobAlertExtractor())->extract(
            'gmail-linkedin-digest-regression',
            'JOB_ALERT',
            "Offres d'emploi similaires à Développeur et Lead Dev PHP (H/F)",
            'LinkedIn <jobs-noreply@linkedin.com>',
            $plainBody,
            $htmlBody,
            new \DateTimeImmutable('2026-08-11T00:00:00+02:00'),
        );

        self::assertCount(1, $offers);
        self::assertSame('Lead Développeur PHP Symfony (H-F)', $offers[0]['title']);
        self::assertSame('Lead Développeur PHP Symfony (H-F)', $offers[0]['description']);
        self::assertSame('TITLE_ONLY_DIGEST', $offers[0]['rawData']['descriptionScope']);
        self::assertTrue($offers[0]['rawData']['messageDigestDetected']);
        self::assertStringNotContainsString('Tech Lead PHP H/F', $offers[0]['description']);
        self::assertStringNotContainsString('ALCYON', $offers[0]['description']);
    }

    public function testMultiOfferLinkedinDigestUsesEachLocalArticleOnly(): void
    {
        $htmlBody = <<<'HTML'
<html><body>
<section>
  <article>
    <h2>Backend Java chez Alpha</h2>
    <p>CDI sur site à Paris. Java 21, Spring Boot et Kafka.</p>
    <a href="https://www.linkedin.com/comm/jobs/view/4411111111?trackingId=java">Backend Java chez Alpha</a>
  </article>
  <article>
    <h2>Lead PHP Symfony chez Beta</h2>
    <p>Mission freelance hybride. PHP 8.3, Symfony 7, API Platform. TJM : 520-580 €.</p>
    <a href="https://www.linkedin.com/comm/jobs/view/4422222222?trackingId=php">Lead PHP Symfony chez Beta</a>
  </article>
</section>
</body></html>
HTML;

        $offers = (new GmailJobAlertExtractor())->extract(
            'gmail-linkedin-local-context',
            'JOB_ALERT',
            'Offres recommandées pour vous',
            'LinkedIn <jobs-noreply@linkedin.com>',
            "Offres recommandées pour vous\nVoir l'offre d'emploi\nVoir l'offre d'emploi",
            $htmlBody,
            new \DateTimeImmutable('2026-08-11T00:00:00+02:00'),
        );

        self::assertCount(2, $offers);
        self::assertSame('LINK_CONTEXT', $offers[0]['rawData']['descriptionScope']);
        self::assertStringContainsString('Java 21', $offers[0]['description']);
        self::assertStringNotContainsString('Symfony 7', $offers[0]['description']);
        self::assertStringNotContainsString('https://', $offers[0]['description']);

        self::assertSame('LINK_CONTEXT', $offers[1]['rawData']['descriptionScope']);
        self::assertStringContainsString('Symfony 7', $offers[1]['description']);
        self::assertStringNotContainsString('Java 21', $offers[1]['description']);
        self::assertSame('Freelance', $offers[1]['contractType']);
        self::assertSame(520, $offers[1]['tjmMin']);
        self::assertSame(580, $offers[1]['tjmMax']);
    }
}
