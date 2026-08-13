<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\PreferenceSignal;
use App\Entity\UserSettings;
use App\Service\JobProfileTechnologyComparisonService;
use App\Service\PreferenceFeatureExtractionService;
use PHPUnit\Framework\TestCase;

final class PreferenceFeatureExtractionServiceTest extends TestCase
{
    private PreferenceFeatureExtractionService $service;
    private UserSettings $settings;

    protected function setUp(): void
    {
        $this->service = new PreferenceFeatureExtractionService(new JobProfileTechnologyComparisonService());
        $this->settings = (new UserSettings())->fill([
            'targetJobs' => ['Développeur PHP Symfony'],
            'skills' => ['PHP', 'Symfony', 'React', 'Docker'],
        ]);
    }

    public function testExtractsOnlyExplainableDimensionsPresentOnTheOffer(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Senior PHP Symfony Developer',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'Freelance',
            'workMode' => 'Hybride',
            'description' => 'Symfony APIs, React and Docker.',
            'salaryMin' => 50000,
            'salaryMax' => 60000,
            'tjmFixed' => 500,
        ]);
        $signal = new PreferenceSignal(
            new Application($job),
            'APPLICATION_SUBMITTED',
            1,
            PreferenceSignal::ORIGIN_USER_DECISION,
        );

        $features = $this->service->extract($signal, $this->settings);

        self::assertSame(['Senior PHP Symfony Developer'], $features['dimensions']['title']);
        self::assertSame(['Paris'], $features['dimensions']['location']);
        self::assertSame(['Freelance'], $features['dimensions']['contractType']);
        self::assertSame(['Hybride'], $features['dimensions']['workMode']);
        self::assertSame(['Example'], $features['dimensions']['company']);
        self::assertContains('PHP', $features['dimensions']['technologies']);
        self::assertContains('Symfony', $features['dimensions']['technologies']);
        self::assertContains('React', $features['dimensions']['technologies']);
        self::assertContains('Docker', $features['dimensions']['technologies']);
        self::assertSame(55000, $features['numeric']['salaryAnnual']);
        self::assertSame(500, $features['numeric']['tjm']);
    }

    public function testSummarizesPositiveNegativeAndPipelineOutcomeEvidenceSeparately(): void
    {
        $submitted = $this->signal(
            'Symfony Backend Developer',
            'Symfony PHP Docker',
            'CDI',
            'Hybride',
            1,
            PreferenceSignal::ORIGIN_USER_DECISION,
            'APPLICATION_SUBMITTED',
        );
        $interview = $this->signal(
            'Symfony Backend Developer',
            'Symfony PHP',
            'CDI',
            'Hybride',
            1,
            PreferenceSignal::ORIGIN_PIPELINE_OUTCOME,
            'INTERVIEW_REACHED',
        );
        $rejectedByUser = $this->signal(
            'React Frontend Developer',
            'React TypeScript',
            'CDI',
            'Remote',
            -1,
            PreferenceSignal::ORIGIN_USER_DECISION,
            'JOB_REJECTED_BY_USER',
        );
        $rejectedByRecruiter = $this->signal(
            'Symfony Backend Developer',
            'Symfony PHP',
            'CDI',
            'Hybride',
            0,
            PreferenceSignal::ORIGIN_PIPELINE_OUTCOME,
            'RECRUITER_REJECTION',
        );

        $summary = $this->service->summarize(
            [$submitted, $interview, $rejectedByUser, $rejectedByRecruiter],
            $this->settings,
        );

        $technologyRows = array_column($summary['technologies'], null, 'value');
        self::assertSame(2, $technologyRows['Symfony']['positive']);
        self::assertSame(0, $technologyRows['Symfony']['negative']);
        self::assertSame(1, $technologyRows['Symfony']['outcomes']);
        self::assertSame(2, $technologyRows['Symfony']['total']);
        self::assertSame(0, $technologyRows['React']['positive']);
        self::assertSame(1, $technologyRows['React']['negative']);
        self::assertSame(0, $technologyRows['React']['outcomes']);

        $workModeRows = array_column($summary['workMode'], null, 'value');
        self::assertSame(2, $workModeRows['Hybride']['positive']);
        self::assertSame(1, $workModeRows['Hybride']['outcomes']);
        self::assertSame(1, $workModeRows['Remote']['negative']);

        // Recruiter rejection is deliberately neutral and must not increase learned preference evidence.
        self::assertSame(2, $workModeRows['Hybride']['total']);
    }

    private function signal(
        string $title,
        string $description,
        string $contractType,
        string $workMode,
        int $preferenceValue,
        string $origin,
        string $signalType,
    ): PreferenceSignal {
        $job = (new JobOffer())->fill([
            'title' => $title,
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => $contractType,
            'workMode' => $workMode,
            'description' => $description,
        ]);

        return new PreferenceSignal(
            new Application($job),
            $signalType,
            $preferenceValue,
            $origin,
        );
    }
}
