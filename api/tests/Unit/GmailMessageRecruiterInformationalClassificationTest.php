<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Messaging\Application\GmailMessageClassifier;
use PHPUnit\Framework\TestCase;

final class GmailMessageRecruiterInformationalClassificationTest extends TestCase
{
    public function testRecruiterHandoffWithoutRequestedActionIsInformational(): void
    {
        $result = (new GmailMessageClassifier())->classify(
            'Re: Mission Symfony',
            'Claire Martin <claire@esn.example>',
            'Merci pour votre retour. Je transmets votre CV au client et je reviens vers vous dès que j’ai un retour.',
        );

        self::assertSame('RECRUITER_INFORMATIONAL', $result['category']);
        self::assertFalse($result['actionRequired']);
        self::assertStringContainsString('sans demander', mb_strtolower($result['reason']));
    }

    public function testEnglishRecruiterAcknowledgementIsInformational(): void
    {
        $result = (new GmailMessageClassifier())->classify(
            'Re: Senior PHP role',
            'Recruiter <talent@example.com>',
            "Thanks for your reply. I have forwarded your CV to the hiring manager and I'll get back to you.",
        );

        self::assertSame('RECRUITER_INFORMATIONAL', $result['category']);
        self::assertFalse($result['actionRequired']);
    }

    public function testInformationRequestKeepsPriorityOverInformationalSignals(): void
    {
        $result = (new GmailMessageClassifier())->classify(
            'Re: Mission Symfony',
            'Claire Martin <claire@esn.example>',
            'Merci pour votre retour. Pouvez-vous nous transmettre vos disponibilités ? Je reviens vers vous ensuite.',
        );

        self::assertSame('INFORMATION_REQUEST', $result['category']);
        self::assertTrue($result['actionRequired']);
    }

    public function testDirectOpportunityIsStillActionableWhenNoAcknowledgementPatternExists(): void
    {
        $result = (new GmailMessageClassifier())->classify(
            'Nouvelle mission Symfony',
            'Claire Martin <claire@esn.example>',
            'Nous recherchons un consultant Symfony. Votre profil a retenu mon attention.',
        );

        self::assertSame('RECRUITER_OPPORTUNITY', $result['category']);
        self::assertTrue($result['actionRequired']);
    }
}
