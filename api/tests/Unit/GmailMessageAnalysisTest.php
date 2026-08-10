<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Messaging\Application\GmailJobAlertExtractor;
use App\Messaging\Application\GmailMessageClassifier;
use App\Messaging\Infrastructure\Gmail\GmailMessageDecoder;
use PHPUnit\Framework\TestCase;

final class GmailMessageAnalysisTest extends TestCase
{
    public function testClassifierRecognizesInterviewsRejectionsAndRecruiterRequests(): void
    {
        $classifier = new GmailMessageClassifier();

        self::assertSame(
            'INTERVIEW_REQUEST',
            $classifier->classify('Invitation entretien technique', 'recruteur@example.com', 'Choisissez un créneau Calendly.')['category'],
        );
        self::assertSame(
            'REJECTION',
            $classifier->classify('Suite à votre candidature', 'jobs@example.com', 'Malheureusement, votre candidature n’a pas été retenue.')['category'],
        );
        $request = $classifier->classify(
            'Mission Symfony',
            'commercial@esn.example',
            'Nous recherchons un freelance. Pouvez-vous nous transmettre votre disponibilité et votre TJM ?',
        );
        self::assertSame('INFORMATION_REQUEST', $request['category']);
        self::assertTrue($request['actionRequired']);
    }

    public function testDecoderReadsNestedMimePartsAndHeaders(): void
    {
        $decoder = new GmailMessageDecoder();
        $message = $decoder->decode([
            'id' => 'gmail-1',
            'threadId' => 'thread-1',
            'internalDate' => '1785888000000',
            'snippet' => 'Aperçu',
            'payload' => [
                'mimeType' => 'multipart/alternative',
                'headers' => [
                    ['name' => 'From', 'value' => 'Jobs <jobs@example.com>'],
                    ['name' => 'To', 'value' => 'aissa@example.com'],
                    ['name' => 'Reply-To', 'value' => 'recruiter@example.com'],
                    ['name' => 'Subject', 'value' => 'Alerte Symfony'],
                ],
                'parts' => [
                    ['mimeType' => 'text/plain', 'body' => ['data' => $this->encode('Offre Symfony')]],
                    ['mimeType' => 'text/html', 'body' => ['data' => $this->encode('<p>Offre <strong>Symfony</strong></p>')]],
                ],
            ],
        ]);

        self::assertSame('gmail-1', $message['gmailMessageId']);
        self::assertSame('Jobs <jobs@example.com>', $message['sender']);
        self::assertSame('recruiter@example.com', $message['replyTo']);
        self::assertSame('Alerte Symfony', $message['subject']);
        self::assertSame('Offre Symfony', $message['plainBody']);
        self::assertStringContainsString('<strong>Symfony</strong>', $message['htmlBody']);
    }

    public function testExtractorKeepsJobLinksAndRejectsUnsubscribeLinks(): void
    {
        $extractor = new GmailJobAlertExtractor();
        $offers = $extractor->extract(
            'gmail-2',
            'JOB_ALERT',
            'Alerte emploi Symfony',
            'APEC <alertes@apec.fr>',
            'Deux offres correspondent à votre recherche.',
            '<a href="https://www.apec.fr/candidat/recherche-emploi.html/emploi/detail-offre/123?utm_source=email">Développeur PHP Symfony chez Example</a>'
                .'<a href="https://www.apec.fr/preferences/unsubscribe">Se désabonner</a>',
            new \DateTimeImmutable('2026-08-05T00:00:00+02:00'),
        );

        self::assertCount(1, $offers);
        self::assertSame('Développeur PHP Symfony', $offers[0]['title']);
        self::assertSame('Example', $offers[0]['company']);
        self::assertSame('APEC', $offers[0]['rawData']['alertPlatform']);
        self::assertSame('apec', $offers[0]['rawData']['alertPlatformCode']);
        self::assertStringNotContainsString('utm_source', $offers[0]['sourceUrl']);
    }

    public function testExtractorRecognizesCurrentAssistedPlatformUrlVariants(): void
    {
        $extractor = new GmailJobAlertExtractor();
        $offers = $extractor->extract(
            'gmail-3',
            'JOB_ALERT',
            'Nouvelles offres Symfony',
            'Alertes <alerts@example.com>',
            '',
            '<a href="https://www.free-work.com/fr/tech-it/job-mission/developpeur-php-symfony-laravel-drupal/developpeur-php-symfony-lille-8">Développeur Symfony chez Acme</a>'
                .'<a href="https://lesjeudis.com/fr/job/lead-developer-php">Lead Developer PHP chez Beta</a>'
                .'<a href="https://www.welcometothejungle.com/fr/pages/terms">Conditions</a>',
            new \DateTimeImmutable('2026-08-10T12:00:00+02:00'),
        );

        self::assertCount(2, $offers);
        self::assertSame('free-work', $offers[0]['rawData']['alertPlatformCode']);
        self::assertSame('Free-Work', $offers[0]['rawData']['alertPlatform']);
        self::assertSame('lesjeudis', $offers[1]['rawData']['alertPlatformCode']);
        self::assertSame('LesJeudis', $offers[1]['rawData']['alertPlatform']);
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
