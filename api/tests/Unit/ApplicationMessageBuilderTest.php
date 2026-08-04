<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Service\ApplicationMessageBuilder;
use PHPUnit\Framework\TestCase;

final class ApplicationMessageBuilderTest extends TestCase
{
    public function testItBuildsAShortFrenchMessageTailoredToTheMission(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Développeur confirmé PHP Symfony',
            'company' => 'Signe +',
            'language' => 'fr',
            'description' => 'Développement backend PHP 8, Symfony, API Platform, Doctrine, Docker et PostgreSQL.',
        ]);

        $content = (new ApplicationMessageBuilder())->build($job, $this->profile());
        $message = $content['message'];

        self::assertStringContainsString('Développeur confirmé PHP Symfony chez Signe +', $message);
        self::assertStringContainsString('11 ans d’expérience', $message);
        self::assertStringContainsString('API Platform, Symfony, PHP et Doctrine', $message);
        self::assertStringContainsString('conception d’API', $message);
        self::assertStringContainsString('Vous trouverez mon CV en pièce jointe.', $message);
        self::assertSame(1, substr_count($message, 'Bonjour,'));
        self::assertSame(1, substr_count($message, 'Bien cordialement,'));
        self::assertStringNotContainsString('---', $message);
        self::assertLessThanOrEqual(115, str_word_count(str_replace('’', "'", $message)));
    }

    public function testItTailorsAFrontendMessageWithoutClaimingBackendFocus(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Senior Frontend Developer',
            'company' => 'Product Studio',
            'language' => 'en',
            'description' => 'Build accessible React, Next.js and TypeScript user interfaces with strong web performance.',
        ]);

        $content = (new ApplicationMessageBuilder())->build($job, $this->profile());
        $message = $content['message'];

        self::assertStringContainsString('React, Next.js and TypeScript', $message);
        self::assertStringContainsString('high-performance user interfaces, accessibility and user experience', $message);
        self::assertStringNotContainsString('API design, application architecture', $message);
        self::assertSame(1, substr_count($message, 'Best regards,'));
        self::assertLessThanOrEqual(110, str_word_count($message));
    }

    public function testItKeepsTheCoverLetterSeparateFromTheEmailMessage(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Développeur Full-Stack',
            'company' => 'Entreprise',
            'language' => 'fr',
            'description' => 'PHP Symfony React TypeScript',
        ]);

        $content = (new ApplicationMessageBuilder())->build($job, $this->profile());

        self::assertNotSame($content['message'], $content['coverLetter']);
        self::assertStringStartsWith('Bonjour,', $content['message']);
        self::assertStringStartsWith('Madame, Monsieur,', $content['coverLetter']);
        self::assertStringNotContainsString('Madame, Monsieur,', $content['message']);
    }

    private function profile(): CandidateProfile
    {
        return (new CandidateProfile())->fill([
            'fullName' => 'Aissa Soubhi',
            'availability' => 'Immédiate',
            'yearsOfExperience' => 11,
        ]);
    }
}
