<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\JobProfileTechnologyComparisonService;
use PHPUnit\Framework\TestCase;

final class JobProfileTechnologyComparisonServiceTest extends TestCase
{
    public function testReusesExistingAiMetadataWithoutAnyProviderCall(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Senior React TypeScript Developer',
            'description' => 'Build React and TypeScript interfaces with Symfony APIs, Docker and Kubernetes.',
        ]);
        $job->setEvaluation(
            'en',
            82,
            [
                'Analyse IA : MATCH · confiance 93%',
                'Stack principale détectée par IA : React, TypeScript',
                'Prérequis principaux manquants : Kubernetes',
                'Explication IA : Strong frontend fit, Kubernetes is the main missing requirement.',
            ],
            null,
            null,
            'PREPARED',
            null,
        );
        $settings = (new UserSettings())->fill([
            'targetJobs' => ['Senior React Developer'],
            'skills' => ['React', 'TypeScript', 'Symfony', 'Docker'],
        ]);

        $comparison = (new JobProfileTechnologyComparisonService())->compare($job, $settings);

        self::assertSame('AI_REUSED', $comparison['source']);
        self::assertSame('MATCH', $comparison['aiDecision']);
        self::assertSame(93, $comparison['aiConfidence']);
        self::assertSame(['React', 'TypeScript'], $comparison['primaryTechnologies']);
        self::assertContains('Symfony', $comparison['matchingTechnologies']);
        self::assertContains('Docker', $comparison['matchingTechnologies']);
        self::assertContains('Kubernetes', $comparison['missingTechnologies']);
        self::assertSame(['Kubernetes'], $comparison['missingMustHaves']);
        self::assertNotContains('Kubernetes', $comparison['missingNiceToHaves']);
    }

    public function testFallsBackDeterministicallyAndDoesNotInventMandatoryGaps(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Senior PHP Symfony Developer',
            'description' => 'PHP Symfony APIs with Docker. Kubernetes is a nice-to-have for the platform.',
        ]);
        $job->setEvaluation(
            'en',
            75,
            ['PHP and Symfony match configured skills.'],
            null,
            null,
            'PREPARED',
            null,
        );
        $settings = (new UserSettings())->fill([
            'targetJobs' => ['Senior PHP Symfony Developer'],
            'skills' => ['PHP', 'Symfony', 'Docker'],
        ]);

        $comparison = (new JobProfileTechnologyComparisonService())->compare($job, $settings);

        self::assertSame('DETERMINISTIC', $comparison['source']);
        self::assertNull($comparison['aiDecision']);
        self::assertNull($comparison['aiConfidence']);
        self::assertContains('PHP', $comparison['primaryTechnologies']);
        self::assertContains('Symfony', $comparison['primaryTechnologies']);
        self::assertContains('Docker', $comparison['matchingTechnologies']);
        self::assertContains('Kubernetes', $comparison['missingTechnologies']);
        self::assertSame([], $comparison['missingMustHaves']);
        self::assertContains('Kubernetes', $comparison['missingNiceToHaves']);
    }
}
