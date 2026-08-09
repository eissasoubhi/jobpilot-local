<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Service\ApplicationContentBuilder;
use App\Service\ApplicationMessageBuilder;
use App\Service\CoverLetterRequirementDetector;
use App\Service\GroundedCoverLetterBuilder;
use PHPUnit\Framework\TestCase;

final class GroundedCoverLetterBuilderTest extends TestCase
{
    public function testFrenchPreparationAlwaysContainsGroundedCoverLetter(): void
    {
        $profile = (new CandidateProfile())->fill([
            'fullName' => 'Test Candidate',
            'yearsOfExperience' => 9,
            'availability' => 'Immédiate',
        ]);
        $job = (new JobOffer())->fill([
            'title' => 'Développeur Backend',
            'company' => 'Example Corp',
            'language' => 'fr',
            'description' => 'Java, Kubernetes et Terraform sont obligatoires.',
        ]);

        $content = $this->contentBuilder()->build($job, $profile);

        self::assertFalse($content['coverLetterRequired']);
        self::assertNotSame('', trim($content['coverLetter']));
        self::assertStringContainsString('Développeur Backend', $content['coverLetter']);
        self::assertStringContainsString('Example Corp', $content['coverLetter']);
        self::assertStringContainsString('9 ans', $content['coverLetter']);
        self::assertStringContainsString('disponible immédiatement', $content['coverLetter']);
        self::assertStringContainsString('Test Candidate', $content['coverLetter']);
        self::assertStringNotContainsString('Java', $content['coverLetter']);
        self::assertStringNotContainsString('Kubernetes', $content['coverLetter']);
        self::assertStringNotContainsString('Terraform', $content['coverLetter']);
    }

    public function testEnglishPreparationUsesOfferLanguageWithoutInventingTechnologies(): void
    {
        $profile = (new CandidateProfile())->fill([
            'fullName' => 'Test Candidate',
            'yearsOfExperience' => 7,
            'availability' => 'Immediate',
        ]);
        $job = (new JobOffer())->fill([
            'title' => 'Platform Engineer',
            'company' => 'Example Ltd',
            'language' => 'en',
            'description' => 'Strong Rust, Kubernetes and AWS expertise required.',
        ]);

        $letter = (new GroundedCoverLetterBuilder())->build($job, $profile);

        self::assertStringStartsWith('Dear Hiring Team,', $letter);
        self::assertStringContainsString('Platform Engineer', $letter);
        self::assertStringContainsString('Example Ltd', $letter);
        self::assertStringContainsString('7 years', $letter);
        self::assertStringContainsString('available immediately', $letter);
        self::assertStringNotContainsString('Rust', $letter);
        self::assertStringNotContainsString('Kubernetes', $letter);
        self::assertStringNotContainsString('AWS', $letter);
    }

    public function testExplicitCoverLetterRequirementRemainsVisibleAsMetadata(): void
    {
        $profile = (new CandidateProfile())->fill([
            'fullName' => 'Test Candidate',
            'yearsOfExperience' => 5,
        ]);
        $job = (new JobOffer())->fill([
            'title' => 'Développeur Web',
            'language' => 'fr',
            'description' => 'Merci de joindre une lettre de motivation à votre candidature.',
        ]);

        $content = $this->contentBuilder()->build($job, $profile);

        self::assertTrue($content['coverLetterRequired']);
        self::assertNotSame('', trim($content['coverLetter']));
    }

    private function contentBuilder(): ApplicationContentBuilder
    {
        return new ApplicationContentBuilder(
            new ApplicationMessageBuilder(),
            new CoverLetterRequirementDetector(),
            new GroundedCoverLetterBuilder(),
        );
    }
}
