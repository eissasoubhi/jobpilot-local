<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\JobProfileTechnologyComparisonService;
use App\Service\RequiredPrimaryTechnologyGuard;
use PHPUnit\Framework\TestCase;

final class RequiredPrimaryTechnologyGuardTest extends TestCase
{
    private RequiredPrimaryTechnologyGuard $guard;
    private UserSettings $settings;

    protected function setUp(): void
    {
        $this->guard = new RequiredPrimaryTechnologyGuard(new JobProfileTechnologyComparisonService());
        $this->settings = (new UserSettings())->fill([
            'targetJobs' => [
                'Senior Full Stack PHP Symfony React Developer',
                'React Developer',
                'Next.js Developer',
            ],
            'skills' => ['PHP', 'Symfony', 'React', 'Next.js', 'TypeScript'],
        ]);
    }

    public function testJavaReactNextFullStackRoleIsRejectedWhenJavaIsMandatoryAndMissing(): void
    {
        $result = $this->guard->evaluate($this->job(
            'Développeur Full Stack Java / React / Next.js (H/F)',
            'Le développeur intervient sur le backend Java et Spring Boot ainsi que sur le frontend React et Next.js.',
        ), $this->settings);

        self::assertTrue($result['hardRejected']);
        self::assertSame(45, $result['scoreCap']);
        self::assertContains('Technologie principale obligatoire manquante : Java.', $result['reasons']);
    }

    public function testJavaReactRoleIsRejectedEvenWhenReactMatches(): void
    {
        $result = $this->guard->evaluate($this->job(
            'Développeur Java / React (H/F)',
            'Le poste demande un développeur Java côté backend et React côté frontend.',
        ), $this->settings);

        self::assertTrue($result['hardRejected']);
        self::assertContains('Technologie principale obligatoire manquante : Java.', $result['reasons']);
    }

    public function testJavaAndReactAngularRoleStillRejectsJavaWhenReactIsSupported(): void
    {
        $result = $this->guard->evaluate($this->job(
            'Développeur Fullstack Java & React / Angular (H/F)',
            'Backend Java obligatoire. Frontend Angular ou React selon la squad.',
        ), $this->settings);

        self::assertTrue($result['hardRejected']);
        self::assertContains('Technologie principale obligatoire manquante : Java.', $result['reasons']);
    }

    public function testUnrelatedFrontendAlternativeCannotWaiveMandatoryJava(): void
    {
        $result = $this->guard->evaluate($this->job(
            'Développeur Fullstack Senior',
            'Backend obligatoire : Java et Spring Boot. Frontend : Angular ou React selon la squad.',
        ), $this->settings);

        self::assertTrue($result['hardRejected']);
        self::assertContains('Technologie principale obligatoire manquante : Java.', $result['reasons']);
    }

    public function testPhpSymfonyAngularRoleIsRejectedWhenAngularIsMandatoryAndMissing(): void
    {
        $result = $this->guard->evaluate($this->job(
            'Développeur PHP Symfony Angular',
            'Le poste est fullstack : PHP 8 et Symfony 7.4 côté backend, Angular 21 côté frontend.',
        ), $this->settings);

        self::assertTrue($result['hardRejected']);
        self::assertSame(45, $result['scoreCap']);
        self::assertContains('Technologie principale obligatoire manquante : Angular.', $result['reasons']);
    }

    public function testGenericCloudBackendRoleIsRejectedWhenDescriptionRequiresCSharp(): void
    {
        $result = $this->guard->evaluate($this->job(
            'Développeur Cloud Backend Senior (H/F)',
            'Vous rejoignez une équipe cloud Azure, GCP et AWS. Développer des parties de la solution avec une maîtrise de C# et des API REST. Vous participerez aux choix techniques de la plateforme.',
        ), $this->settings);

        self::assertTrue($result['hardRejected']);
        self::assertSame(45, $result['scoreCap']);
        self::assertContains('Technologie principale obligatoire manquante : .NET / C#.', $result['reasons']);
    }

    public function testAiMissingAngularMustHaveIsRejectedEvenWithGenericTitle(): void
    {
        $job = $this->job(
            'Développeur PHP Fullstack',
            'Le rôle couvre le backend PHP Symfony et le frontend Angular.',
        );
        $job->refreshMatchingScore(75, [
            'Analyse IA : REVIEW · confiance 90%',
            'Stack principale détectée par IA : PHP, Symfony, Angular',
            'Prérequis principaux manquants : Angular',
        ]);

        $result = $this->guard->evaluate($job, $this->settings);

        self::assertTrue($result['hardRejected']);
        self::assertContains('Technologie principale obligatoire manquante : Angular.', $result['reasons']);
    }

    public function testSecondaryJavaMentionDoesNotRejectASymfonyRole(): void
    {
        $result = $this->guard->evaluate($this->job(
            'Senior Symfony React Developer',
            'Le poste est en PHP Symfony et React. Une connaissance de Java est appréciée pour une intégration historique.',
        ), $this->settings);

        self::assertFalse($result['hardRejected']);
        self::assertNull($result['scoreCap']);
    }

    public function testSecondaryAngularMentionDoesNotRejectAReactRole(): void
    {
        $result = $this->guard->evaluate($this->job(
            'Senior Symfony React Developer',
            'Le poste principal est PHP Symfony et React. Angular est seulement un plus pour une ancienne interface.',
        ), $this->settings);

        self::assertFalse($result['hardRejected']);
        self::assertNull($result['scoreCap']);
    }

    public function testExplicitJavaOrPhpAlternativeRemainsEligibleWhenPhpMatches(): void
    {
        $result = $this->guard->evaluate($this->job(
            'Senior Backend Java or PHP Developer',
            'Deux stacks sont acceptées selon le projet : Java Spring ou PHP Symfony.',
        ), $this->settings);

        self::assertFalse($result['hardRejected']);
        self::assertNull($result['scoreCap']);
    }

    public function testExplicitAngularOrReactAlternativeRemainsEligibleWhenReactMatches(): void
    {
        $result = $this->guard->evaluate($this->job(
            'Frontend Angular ou React Developer',
            'Le produit accepte Angular ou React selon la squad.',
        ), $this->settings);

        self::assertFalse($result['hardRejected']);
        self::assertNull($result['scoreCap']);
    }

    public function testGenericTitleWithAngularOrReactAlternativeInDescriptionRemainsEligible(): void
    {
        $result = $this->guard->evaluate($this->job(
            'Développeur Frontend Senior',
            'Stack frontend obligatoire : Angular ou React selon la squad. TypeScript est utilisé partout.',
        ), $this->settings);

        self::assertFalse($result['hardRejected']);
        self::assertNull($result['scoreCap']);
    }

    public function testIndependentBackendAndFrontendAlternativesRemainEligibleWhenBothSupported(): void
    {
        $result = $this->guard->evaluate($this->job(
            'Développeur Fullstack Senior',
            'Backend obligatoire : Java ou PHP. Frontend obligatoire : Angular ou React.',
        ), $this->settings);

        self::assertFalse($result['hardRejected']);
        self::assertNull($result['scoreCap']);
    }

    private function job(string $title, string $description): JobOffer
    {
        return (new JobOffer())->fill([
            'title' => $title,
            'description' => $description,
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybrid',
        ]);
    }
}
