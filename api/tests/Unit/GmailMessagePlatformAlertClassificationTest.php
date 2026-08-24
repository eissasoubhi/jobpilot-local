<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Messaging\Application\GmailMessageClassifier;
use PHPUnit\Framework\TestCase;

final class GmailMessagePlatformAlertClassificationTest extends TestCase
{
    public function testJobijobaDigestMentioningSeveralCompaniesIsNotARecruiterOpportunity(): void
    {
        $result = (new GmailMessageClassifier())->classify(
            'OKTOGONE, OPEN et agap2IT vous proposent des offres',
            'Jobijoba <alertes@jobijoba.com>',
            'Découvrez les offres correspondant à votre recherche. Plusieurs entreprises recherchent des profils Symfony.',
        );

        self::assertSame('JOB_ALERT', $result['category']);
        self::assertFalse($result['actionRequired']);
        self::assertStringContainsString('plateforme', mb_strtolower($result['reason']));
    }

    public function testDirectRecruiterOpportunityStillRequiresAction(): void
    {
        $result = (new GmailMessageClassifier())->classify(
            'Mission Symfony',
            'Claire Martin <claire@esn.example>',
            'Nous recherchons un freelance Symfony pour une nouvelle mission. TJM 600 €.',
        );

        self::assertSame('RECRUITER_OPPORTUNITY', $result['category']);
        self::assertTrue($result['actionRequired']);
    }

    public function testTransactionalInterviewKeepsPriorityOverPlatformAlertClassification(): void
    {
        $result = (new GmailMessageClassifier())->classify(
            'Invitation entretien technique',
            'LinkedIn <messages-noreply@linkedin.com>',
            'Choisissez un créneau Calendly pour votre entretien.',
        );

        self::assertSame('INTERVIEW_REQUEST', $result['category']);
        self::assertTrue($result['actionRequired']);
    }
}
