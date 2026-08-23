<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Entity\JobSourceOccurrence;
use App\Service\JobPriorityScoreService;
use PHPUnit\Framework\TestCase;

final class JobPriorityScoreServiceTest extends TestCase
{
    private JobPriorityScoreService $service;

    protected function setUp(): void
    {
        $this->service = new JobPriorityScoreService();
    }

    public function testStrongMatchCanBeatMuchFresherWeakerOffer(): void
    {
        $profile = $this->profile();
        $strong = $this->job(95, '-4 days', 'CDI', 'Paris', 'Hybride', 55000);
        $freshButWeaker = $this->job(70, '-2 hours', 'CDI', 'Paris', 'Hybride', 55000);

        $strongPriority = $this->service->evaluate($strong, $profile);
        $freshPriority = $this->service->evaluate($freshButWeaker, $profile);

        self::assertGreaterThan($freshPriority['score'], $strongPriority['score']);
        self::assertSame(95, $strongPriority['components']['match']);
        self::assertLessThan($freshPriority['components']['freshness'], $strongPriority['components']['freshness']);
    }

    public function testFreshnessUsesSmoothDecayInsteadOfAbruptBuckets(): void
    {
        $profile = $this->profile();
        $beforeBoundary = $this->job(80, '-23 hours', 'CDI', 'Paris', 'Hybride', 55000);
        $afterBoundary = $this->job(80, '-25 hours', 'CDI', 'Paris', 'Hybride', 55000);

        $before = $this->service->evaluate($beforeBoundary, $profile);
        $after = $this->service->evaluate($afterBoundary, $profile);

        self::assertLessThanOrEqual(2, abs($before['score'] - $after['score']));
        self::assertGreaterThan($after['components']['freshness'], $before['components']['freshness']);
    }

    public function testMissingPublicationDateIsNeutralInsteadOfAssumedStale(): void
    {
        $profile = $this->profile();
        $unknown = $this->job(80, '-1 hour', 'CDI', 'Paris', 'Hybride', 55000);
        $unknown->fill(['publishedAt' => null]);
        $stale = $this->job(80, '-10 days', 'CDI', 'Paris', 'Hybride', 55000);

        $unknownPriority = $this->service->evaluate($unknown, $profile);
        $stalePriority = $this->service->evaluate($stale, $profile);

        self::assertSame(50, $unknownPriority['components']['freshness']);
        self::assertGreaterThan($stalePriority['components']['freshness'], $unknownPriority['components']['freshness']);
        self::assertGreaterThan($stalePriority['score'], $unknownPriority['score']);
    }

    public function testPreferencesAndCompensationImprovePriority(): void
    {
        $profile = $this->profile();
        $matchingPreferences = $this->job(80, '-12 hours', 'CDI', 'Paris', 'Hybride', 55000);
        $poorPreferences = $this->job(80, '-12 hours', 'Freelance', 'Lyon', 'Présentiel', null);

        $good = $this->service->evaluate($matchingPreferences, $profile);
        $poor = $this->service->evaluate($poorPreferences, $profile);

        self::assertGreaterThan($poor['components']['preferences'], $good['components']['preferences']);
        self::assertGreaterThan($poor['components']['compensation'], $good['components']['compensation']);
        self::assertGreaterThan($poor['score'], $good['score']);
    }

    public function testCompensationUsesRangeMidpointInsteadOfOptimisticUpperBound(): void
    {
        $profile = (new CandidateProfile())->fill([
            'preferredLocations' => ['Paris'],
            'acceptedContracts' => ['CDI'],
            'workModePreference' => 'Hybride',
            'desiredSalary' => 55000,
        ]);
        $job = $this->job(80, '-12 hours', 'CDI', 'Paris', 'Hybride', null);
        $job->fill([
            'salaryMin' => 40000,
            'salaryMax' => 60000,
        ]);

        $priority = $this->service->evaluate($job, $profile);

        self::assertSame(80, $priority['components']['compensation']);
    }

    public function testAiConfidenceAndHistoricalConversionAreUsedWithShrinkage(): void
    {
        $profile = $this->profile();
        $job = $this->job(85, '-8 hours', 'CDI', 'Paris', 'Hybride', 55000, [
            'Analyse IA : MATCH · confiance 90%',
        ]);

        $priority = $this->service->evaluate($job, $profile, [
            'manual' => [
                'code' => 'manual',
                'submitted' => 20,
                'responseRate' => 80.0,
                'interviewRate' => 70.0,
            ],
        ]);

        self::assertGreaterThanOrEqual(80, $priority['components']['confidence']);
        self::assertGreaterThan(50, $priority['components']['history']);
        self::assertCount(6, $priority['reasons']);
    }

    public function testMultiSourceHistoryPoolsEvidenceInsteadOfTakingBestSource(): void
    {
        $profile = $this->profile();
        $job = $this->job(85, '-8 hours', 'CDI', 'Paris', 'Hybride', 55000);
        new JobSourceOccurrence($job, 'source-a', 'Source A', 'a-1');
        new JobSourceOccurrence($job, 'source-b', 'Source B', 'b-1');

        $priority = $this->service->evaluate($job, $profile, [
            'source-a' => [
                'submitted' => 20,
                'responseRate' => 100.0,
                'interviewRate' => 100.0,
            ],
            'source-b' => [
                'submitted' => 20,
                'responseRate' => 0.0,
                'interviewRate' => 0.0,
            ],
        ]);

        self::assertSame(50, $priority['components']['history']);
    }

    public function testHardRejectedOfferHasZeroPriority(): void
    {
        $profile = $this->profile();
        $job = $this->job(99, '-1 hour', 'CDI', 'Paris', 'Hybride', 60000);
        $job->setEvaluation('fr', 99, [], null, 60000, 'REJECTED_BY_FILTER', null);

        $priority = $this->service->evaluate($job, $profile);

        self::assertSame(0, $priority['score']);
        self::assertStringContainsString('filtre bloquant', $priority['reasons'][0]);
    }

    private function profile(): CandidateProfile
    {
        return (new CandidateProfile())->fill([
            'preferredLocations' => ['Paris', 'Île-de-France'],
            'acceptedContracts' => ['CDI'],
            'workModePreference' => 'Hybride',
            'desiredSalary' => 50000,
            'desiredTjm' => 500,
        ]);
    }

    /** @param list<string> $reasons */
    private function job(
        int $matchScore,
        string $publishedAt,
        string $contract,
        string $location,
        string $workMode,
        ?int $salary,
        array $reasons = [],
    ): JobOffer {
        $job = (new JobOffer())->fill([
            'source' => 'Manual',
            'sourceCode' => 'manual',
            'title' => 'Senior PHP Symfony Developer',
            'company' => 'Example',
            'location' => $location,
            'contractType' => $contract,
            'workMode' => $workMode,
            'description' => str_repeat('Symfony PHP API React architecture testing. ', 12),
            'publishedAt' => (new \DateTimeImmutable($publishedAt))->format(DATE_ATOM),
            'salaryMin' => $salary,
            'salaryMax' => $salary,
        ]);
        $job->setEvaluation('fr', $matchScore, $reasons, null, $salary, 'PREPARED', null);

        return $job;
    }
}
