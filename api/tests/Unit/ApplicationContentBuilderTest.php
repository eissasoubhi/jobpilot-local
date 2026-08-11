<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Service\ApplicationContentBuilder;
use App\Service\ApplicationMessageBuilder;
use App\Service\CoverLetterRequirementDetector;
use PHPUnit\Framework\TestCase;

final class ApplicationContentBuilderTest extends TestCase
{
    public function testKeepsTheApplicationEmailAndPreparesAGroundedMediumLengthLetter(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Développeur PHP Symfony',
            'company' => 'Entreprise',
            'language' => 'fr',
            'description' => 'Conception d’API avec Symfony, Doctrine, PostgreSQL et Kubernetes. Envoyez votre CV.',
        ]);

        $content = $this->builder()->build(
            $job,
            $this->profile(),
            ['PHP', 'Symfony', 'Doctrine', 'PostgreSQL', 'React'],
        );

        self::assertFalse($content['coverLetterRequired']);
        self::assertStringStartsWith('Madame, Monsieur,', $content['coverLetter']);
        self::assertStringContainsString('Développeur PHP Symfony', $content['coverLetter']);
        self::assertStringContainsString('PHP, Symfony, Doctrine et PostgreSQL', $content['coverLetter']);
        self::assertStringNotContainsString('Kubernetes', $content['coverLetter']);
        self::assertStringNotContainsString('React', $content['coverLetter']);
        self::assertWordCountBetween($content['coverLetter'], 150, 220);
        self::assertStringStartsWith('Bonjour,', $content['message']);
    }

    public function testKeepsTheLetterSeparateWhenTheOfferExplicitlyRequestsIt(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Senior Full-Stack Developer',
            'company' => 'Product Company',
            'language' => 'en',
            'description' => 'React, Next.js, Symfony and Kubernetes. Please attach a cover letter with your resume.',
        ]);

        $content = $this->builder()->build(
            $job,
            $this->profile(),
            ['React', 'Next.js', 'Symfony', 'TypeScript'],
        );

        self::assertTrue($content['coverLetterRequired']);
        self::assertStringStartsWith('Dear Hiring Team,', $content['coverLetter']);
        self::assertStringContainsString('React, Next.js and Symfony', $content['coverLetter']);
        self::assertStringNotContainsString('Kubernetes', $content['coverLetter']);
        self::assertStringNotContainsString('TypeScript', $content['coverLetter']);
        self::assertWordCountBetween($content['coverLetter'], 150, 220);
        self::assertStringStartsWith('Hello,', $content['message']);
        self::assertStringNotContainsString($content['coverLetter'], $content['message']);
    }

    public function testStillBuildsAMediumLengthLetterWhenNoConfiguredSkillMatches(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Développeur Web Front-End',
            'company' => 'Entreprise',
            'language' => 'fr',
            'description' => 'HTML, CSS et accessibilité web.',
        ]);

        $content = $this->builder()->build($job, $this->profile(), ['Symfony', 'React']);

        self::assertStringContainsString('Les responsabilités décrites dans votre offre', $content['coverLetter']);
        self::assertStringNotContainsString('Symfony', $content['coverLetter']);
        self::assertStringNotContainsString('React', $content['coverLetter']);
        self::assertWordCountBetween($content['coverLetter'], 150, 220);
    }

    private static function assertWordCountBetween(string $value, int $minimum, int $maximum): void
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        $count = $normalized === '' ? 0 : count(explode(' ', $normalized));

        self::assertGreaterThanOrEqual($minimum, $count, sprintf('Expected at least %d words, got %d.', $minimum, $count));
        self::assertLessThanOrEqual($maximum, $count, sprintf('Expected at most %d words, got %d.', $maximum, $count));
    }

    private function builder(): ApplicationContentBuilder
    {
        return new ApplicationContentBuilder(
            new ApplicationMessageBuilder(),
            new CoverLetterRequirementDetector(),
        );
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
