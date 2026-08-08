<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\Ai\AiJobMatchAnalysis;
use App\Service\Ai\AiJobMatchAnalyzerInterface;
use App\Service\MatchingScoreService;
use PHPUnit\Framework\TestCase;

final class MatchingScoreServiceAiTest extends TestCase
{
    public function testUsesAiScoreWhenAnalyzerReturnsAValidAnalysis(): void
    {
        $analyzer = new class implements AiJobMatchAnalyzerInterface {
            public function analyze(JobOffer $job, UserSettings $settings): ?AiJobMatchAnalysis
            {
                return new AiJobMatchAnalysis(
                    27,
                    0.93,
                    'NO_MATCH',
                    'Senior Java Backend Developer',
                    ['Java', 'Spring Boot'],
                    ['PHP'],
                    ['Java'],
                    ['PHP'],
                    ['Java'],
                    ['Primary stack conflicts with the candidate profile.'],
                    'The job is primarily Java despite an incidental PHP mention.',
                );
            }
        };
        $service = new MatchingScoreService($analyzer);

        $result = $service->evaluate($this->job(), $this->settings());

        self::assertSame(27, $result['score']);
        self::assertFalse($result['hardRejected']);
        self::assertContains('Stack principale détectée par IA : Java, Spring Boot', $result['reasons']);
        self::assertContains('Analyse IA : NO_MATCH · confiance 93%', $result['reasons']);
    }

    public function testFallsBackToDeterministicMatcherWhenAiIsUnavailable(): void
    {
        $analyzer = new class implements AiJobMatchAnalyzerInterface {
            public function analyze(JobOffer $job, UserSettings $settings): ?AiJobMatchAnalysis
            {
                return null;
            }
        };
        $service = new MatchingScoreService($analyzer);

        $result = $service->evaluate($this->job(), $this->settings());

        self::assertSame(45, $result['score']);
        self::assertContains('Stack principale détectée : Java/Spring', $result['reasons']);
        self::assertContains('Conflit de stack principale avec le profil : score plafonné à 45/100', $result['reasons']);
    }

    public function testExplicitExclusionStillWinsBeforeAi(): void
    {
        $analyzer = new class implements AiJobMatchAnalyzerInterface {
            public int $calls = 0;

            public function analyze(JobOffer $job, UserSettings $settings): ?AiJobMatchAnalysis
            {
                ++$this->calls;

                return new AiJobMatchAnalysis(99, 1.0, 'MATCH', 'Any', [], [], [], [], [], [], 'Any');
            }
        };
        $service = new MatchingScoreService($analyzer);
        $settings = (new UserSettings())->fill([
            'targetJobs' => ['Senior PHP Symfony Developer'],
            'skills' => ['PHP', 'Symfony'],
            'exclusions' => ['legacy migration'],
        ]);

        $result = $service->evaluate($this->job(), $settings);

        self::assertSame(0, $result['score']);
        self::assertTrue($result['hardRejected']);
        self::assertSame(0, $analyzer->calls);
    }

    private function job(): JobOffer
    {
        return (new JobOffer())->fill([
            'title' => 'Senior Backend Java Developer',
            'description' => 'Java Spring is the primary stack. PHP appears only in a legacy migration context.',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybrid',
        ]);
    }

    private function settings(): UserSettings
    {
        return (new UserSettings())->fill([
            'targetJobs' => ['Senior PHP Symfony Developer', 'Backend PHP Developer'],
            'skills' => ['PHP', 'Symfony', 'React'],
        ]);
    }
}
