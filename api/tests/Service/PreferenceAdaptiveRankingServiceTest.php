<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\PreferenceSignal;
use App\Entity\UserSettings;
use App\Service\JobProfileTechnologyComparisonService;
use App\Service\PreferenceAdaptiveRankingService;
use App\Service\PreferenceFeatureExtractionService;
use PHPUnit\Framework\TestCase;

final class PreferenceAdaptiveRankingServiceTest extends TestCase
{
    private PreferenceAdaptiveRankingService $service;
    private UserSettings $settings;

    protected function setUp(): void
    {
        $features = new PreferenceFeatureExtractionService(new JobProfileTechnologyComparisonService());
        $this->service = new PreferenceAdaptiveRankingService($features);
        $this->settings = (new UserSettings())->fill([
            'targetJobs' => ['Développeur PHP Symfony'],
            'skills' => ['PHP', 'Symfony', 'React', 'Docker'],
        ]);
    }

    public function testColdStartNeverChangesCompatibilityScore(): void
    {
        $job = $this->job('Symfony Backend Developer', 'Symfony PHP Docker', 'CDI', 'Hybride');
        $signals = [
            $this->signal($job, 1, PreferenceSignal::ORIGIN_USER_DECISION, 'APPLICATION_SUBMITTED'),
            $this->signal($job, -1, PreferenceSignal::ORIGIN_USER_DECISION, 'JOB_REJECTED_BY_USER'),
        ];

        $result = $this->service->rank($job, $this->settings, $signals, $this->base(80));

        self::assertSame(80, $result['score']);
        self::assertSame(80, $result['compatibilityScore']);
        self::assertSame(0, $result['preferenceAdjustment']);
        self::assertSame([], $result['preferenceReasons']);
    }

    public function testRepeatedPositiveDecisionsCanBoostButNeverMoreThanTwelvePoints(): void
    {
        $target = $this->job('Symfony Backend Developer', 'Symfony PHP Docker', 'CDI', 'Hybride');
        $signals = [];
        for ($index = 0; $index < 8; ++$index) {
            $signals[] = $this->signal(
                $this->job('Symfony Backend Developer', 'Symfony PHP Docker', 'CDI', 'Hybride'),
                1,
                PreferenceSignal::ORIGIN_USER_DECISION,
                'APPLICATION_SUBMITTED',
            );
        }

        $result = $this->service->rank($target, $this->settings, $signals, $this->base(82));

        self::assertGreaterThan(82, $result['score']);
        self::assertLessThanOrEqual(94, $result['score']);
        self::assertLessThanOrEqual(12, $result['preferenceAdjustment']);
        self::assertNotEmpty($result['preferenceReasons']);
        self::assertStringContainsString('Préférences apprises', implode(' ', $result['reasons']));
    }

    public function testExplicitNegativePreferencesCanLowerAnOtherwiseCompatibleOffer(): void
    {
        $target = $this->job('React Frontend Developer', 'React TypeScript', 'CDI', 'Remote');
        $signals = [
            $this->signal($this->job('React Frontend Developer', 'React TypeScript', 'CDI', 'Remote'), -1, PreferenceSignal::ORIGIN_USER_DECISION, 'JOB_REJECTED_BY_USER'),
            $this->signal($this->job('React Frontend Developer', 'React TypeScript', 'CDI', 'Remote'), -1, PreferenceSignal::ORIGIN_USER_DECISION, 'JOB_REJECTED_BY_USER'),
            $this->signal($this->job('React Frontend Developer', 'React TypeScript', 'CDI', 'Remote'), -1, PreferenceSignal::ORIGIN_USER_DECISION, 'JOB_REJECTED_BY_USER'),
            $this->signal($this->job('Symfony Backend Developer', 'Symfony PHP', 'CDI', 'Hybride'), 1, PreferenceSignal::ORIGIN_USER_DECISION, 'APPLICATION_SUBMITTED'),
        ];

        $result = $this->service->rank($target, $this->settings, $signals, $this->base(88));

        self::assertLessThan(88, $result['score']);
        self::assertGreaterThanOrEqual(76, $result['score']);
        self::assertGreaterThanOrEqual(-12, $result['preferenceAdjustment']);
        self::assertStringContainsString('défavorise', implode(' ', $result['preferenceReasons']));
    }

    public function testRecruiterOutcomesHaveHalfWeightAndRejectionRemainsNeutral(): void
    {
        $target = $this->job('Symfony Backend Developer', 'Symfony PHP', 'CDI', 'Hybride');
        $signals = [
            $this->signal($target, 1, PreferenceSignal::ORIGIN_PIPELINE_OUTCOME, 'INTERVIEW_REACHED'),
            $this->signal($target, 1, PreferenceSignal::ORIGIN_PIPELINE_OUTCOME, 'RECRUITER_RESPONSE'),
            $this->signal($target, 0, PreferenceSignal::ORIGIN_PIPELINE_OUTCOME, 'RECRUITER_REJECTION'),
            $this->signal($this->job('React Frontend Developer', 'React TypeScript', 'CDI', 'Remote'), -1, PreferenceSignal::ORIGIN_USER_DECISION, 'JOB_REJECTED_BY_USER'),
        ];

        $result = $this->service->rank($target, $this->settings, $signals, $this->base(80));

        self::assertGreaterThan(80, $result['score']);
        self::assertLessThanOrEqual(92, $result['score']);
        self::assertSame(3, $result['preferenceEvidence']);
    }

    public function testHardRejectionCanNeverBeReopenedByPreferenceLearning(): void
    {
        $job = $this->job('Symfony Backend Developer', 'Symfony PHP Docker', 'CDI', 'Hybride');
        $signals = array_fill(0, 10, $this->signal($job, 1, PreferenceSignal::ORIGIN_USER_DECISION, 'APPLICATION_SUBMITTED'));

        $result = $this->service->rank($job, $this->settings, $signals, [
            'score' => 0,
            'reasons' => ['Exclusion détectée : interdit'],
            'hardRejected' => true,
        ]);

        self::assertSame(0, $result['score']);
        self::assertSame(0, $result['preferenceAdjustment']);
        self::assertTrue($result['hardRejected']);
        self::assertSame(['Exclusion détectée : interdit'], $result['reasons']);
    }

    /** @return array{score: int, reasons: list<string>, hardRejected: bool} */
    private function base(int $score): array
    {
        return ['score' => $score, 'reasons' => ['Compatibilité de base'], 'hardRejected' => false];
    }

    private function job(string $title, string $description, string $contractType, string $workMode): JobOffer
    {
        return (new JobOffer())->fill([
            'title' => $title,
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => $contractType,
            'workMode' => $workMode,
            'description' => $description,
        ]);
    }

    private function signal(JobOffer $job, int $value, string $origin, string $type): PreferenceSignal
    {
        return new PreferenceSignal(new Application($job), $type, $value, $origin);
    }
}
