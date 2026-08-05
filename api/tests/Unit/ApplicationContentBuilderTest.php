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
    public function testKeepsTheApplicationEmailButOmitsAnUnrequestedCoverLetter(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Développeur PHP Symfony',
            'company' => 'Entreprise',
            'language' => 'fr',
            'description' => 'Conception d’API avec Symfony, Doctrine et PostgreSQL. Envoyez votre CV.',
        ]);

        $content = $this->builder()->build($job, $this->profile());

        self::assertFalse($content['coverLetterRequired']);
        self::assertSame('', $content['coverLetter']);
        self::assertStringStartsWith('Bonjour,', $content['message']);
    }

    public function testKeepsTheLetterSeparateWhenTheOfferExplicitlyRequestsIt(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Senior Full-Stack Developer',
            'company' => 'Product Company',
            'language' => 'en',
            'description' => 'React, Next.js and Symfony. Please attach a cover letter with your resume.',
        ]);

        $content = $this->builder()->build($job, $this->profile());

        self::assertTrue($content['coverLetterRequired']);
        self::assertStringStartsWith('Dear Hiring Team,', $content['coverLetter']);
        self::assertStringStartsWith('Hello,', $content['message']);
        self::assertStringNotContainsString($content['coverLetter'], $content['message']);
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
