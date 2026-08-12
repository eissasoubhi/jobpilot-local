<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\JobProfileTechnologyComparisonService;
use App\Service\MarketSkillsReportService;
use PHPUnit\Framework\TestCase;

final class MarketSkillsReportServiceTest extends TestCase
{
    public function testSummarizesDemandedMatchingAndUnconfiguredTechnologiesWithoutInventingSkills(): void
    {
        $settings = (new UserSettings())->fill([
            'targetJobs' => ['Développeur PHP Symfony', 'Full-Stack Symfony React'],
            'skills' => ['PHP', 'Symfony', 'React', 'Docker'],
        ]);

        $jobs = [
            (new JobOffer())->fill([
                'title' => 'Senior PHP Symfony Developer',
                'description' => 'PHP Symfony APIs with Docker and Kubernetes.',
            ]),
            (new JobOffer())->fill([
                'title' => 'Full-Stack Symfony React',
                'description' => 'Symfony, React, PHP and Docker are used daily.',
            ]),
            (new JobOffer())->fill([
                'title' => 'Symfony React Engineer',
                'description' => 'Symfony, React and TypeScript. Kubernetes knowledge is appreciated.',
            ]),
        ];

        $report = (new MarketSkillsReportService(new JobProfileTechnologyComparisonService()))
            ->summarize($jobs, $settings);

        self::assertSame(3, $report['analyzedJobs']);
        self::assertSame(4, $report['configuredSkillsCount']);
        self::assertSame('Symfony', $report['demanded'][0]['label']);
        self::assertSame(3, $report['demanded'][0]['count']);
        self::assertSame(100.0, $report['demanded'][0]['coveragePercent']);

        $matching = array_column($report['matching'], 'count', 'label');
        self::assertSame(3, $matching['Symfony']);
        self::assertSame(2, $matching['React']);
        self::assertSame(2, $matching['PHP']);
        self::assertSame(2, $matching['Docker']);

        $unconfigured = array_column($report['unconfigured'], 'count', 'label');
        self::assertSame(2, $unconfigured['Kubernetes']);
        self::assertSame(1, $unconfigured['TypeScript']);
    }

    public function testReturnsEmptySignalsWhenNoQualifiedJobsAreProvided(): void
    {
        $settings = (new UserSettings())->fill([
            'skills' => ['PHP', 'Symfony'],
        ]);

        $report = (new MarketSkillsReportService(new JobProfileTechnologyComparisonService()))
            ->summarize([], $settings);

        self::assertSame(0, $report['analyzedJobs']);
        self::assertSame(2, $report['configuredSkillsCount']);
        self::assertSame([], $report['demanded']);
        self::assertSame([], $report['matching']);
        self::assertSame([], $report['unconfigured']);
    }
}
