<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Messaging\Application\GmailMessageClassifier;
use PHPUnit\Framework\TestCase;

final class GmailMessageMarketingClassificationTest extends TestCase
{
    public function testNewsletterIsNonActionableMarketingEvenWhenItMentionsJobOpportunities(): void
    {
        $result = (new GmailMessageClassifier())->classify(
            'Newsletter — conseils carrière et opportunités de la semaine',
            'Career Media <newsletter@example.com>',
            'Découvrez nos conseils carrière et les tendances du marché. De nouvelles opportunités sont publiées cette semaine. Se désabonner.',
        );

        self::assertSame('MARKETING', $result['category']);
        self::assertFalse($result['actionRequired']);
        self::assertStringContainsString('newsletter', mb_strtolower($result['reason']));
    }

    public function testPromotionalBulkContentRequiresBothMarketingAndBulkSignals(): void
    {
        $result = (new GmailMessageClassifier())->classify(
            'Les tendances du marché tech',
            'Media RH <hello@example.com>',
            'Découvrez nos conseils carrière et notre contenu sponsorisé. Manage email preferences or unsubscribe.',
        );

        self::assertSame('MARKETING', $result['category']);
        self::assertFalse($result['actionRequired']);
    }

    public function testRealInterviewInvitationStillHasTransactionalPriority(): void
    {
        $result = (new GmailMessageClassifier())->classify(
            'Invitation entretien technique',
            'LinkedIn <messages-noreply@linkedin.com>',
            'Choisissez votre créneau Calendly. Manage email preferences.',
        );

        self::assertSame('INTERVIEW_REQUEST', $result['category']);
        self::assertTrue($result['actionRequired']);
    }

    public function testDirectRecruiterOpportunityWithoutNewsletterEvidenceRemainsActionable(): void
    {
        $result = (new GmailMessageClassifier())->classify(
            'Nouvelle mission Symfony',
            'Claire Martin <claire@esn.example>',
            'Votre profil a retenu mon attention pour une nouvelle mission Symfony. Êtes-vous disponible ?',
        );

        self::assertSame('RECRUITER_OPPORTUNITY', $result['category']);
        self::assertTrue($result['actionRequired']);
    }
}
