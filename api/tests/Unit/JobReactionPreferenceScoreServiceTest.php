<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Service\JobReactionPreferenceScoreService;
use PHPUnit\Framework\TestCase;

final class JobReactionPreferenceScoreServiceTest extends TestCase
{
    private JobReactionPreferenceScoreService $service;

    protected function setUp(): void
    {
        $this->service = new JobReactionPreferenceScoreService();
    }

    public function testNoHistoryIsNeutral(): void
    {
        $result = $this->service->evaluate($this->job('Développeur PHP Symfony', 'PHP Symfony API'), []);

        self::assertSame(50, $result['score']);
        self::assertSame(0, $result['adjustment']);
        self::assertSame(0, $result['evidence']);
    }

    public function testSubmittedSimilarOffersIncreasePreferenceGradually(): void
    {
        $target = $this->job('Senior PHP Symfony Developer', 'PHP Symfony API Platform React');
        $applications = [
            $this->decision($this->job('Développeur PHP Symfony', 'PHP Symfony API Platform'), 'SUBMITTED'),
            $this->decision($this->job('Backend PHP Symfony Engineer', 'Symfony PHP REST API'), 'SUBMITTED'),
            $this->decision($this->job('PHP Symfony Full Stack', 'PHP Symfony React TypeScript'), 'SUBMITTED'),
        ];

        $result = $this->service->evaluate($target, $applications);

        self::assertGreaterThan(50, $result['score']);
        self::assertGreaterThan(0, $result['adjustment']);
        self::assertSame(3, $result['evidence']);
        self::assertLessThanOrEqual(6, $result['adjustment']);
    }

    public function testIgnoredSimilarOffersDecreasePreference(): void
    {
        $target = $this->job('Java Spring Backend Developer', 'Java Spring Boot microservices');
        $applications = [
            $this->decision($this->job('Senior Java Spring Developer', 'Java Spring Boot API'), 'IGNORED_NOT_MATCH'),
            $this->decision($this->job('Backend Java Engineer', 'Java Spring microservices'), 'IGNORED_NOT_MATCH'),
        ];

        $result = $this->service->evaluate($target, $applications);

        self::assertLessThan(50, $result['score']);
        self::assertLessThan(0, $result['adjustment']);
    }

    public function testContradictorySimilarSignalsStayNearNeutral(): void
    {
        $target = $this->job('React Frontend Developer', 'React TypeScript Next.js');
        $positive = $this->decision($this->job('React Developer', 'React TypeScript Next.js'), 'SUBMITTED');
        $negative = $this->decision($this->job('React Developer', 'React TypeScript Next.js'), 'IGNORED_NOT_MATCH');

        $result = $this->service->evaluate($target, [$positive, $negative]);

        self::assertSame(50, $result['score']);
        self::assertSame(0, $result['adjustment']);
    }

    public function testUnavailableDecisionIsNeutral(): void
    {
        $target = $this->job('PHP Symfony Developer', 'PHP Symfony API');
        $unavailable = $this->decision($this->job('PHP Symfony Developer', 'PHP Symfony API'), 'OFFER_UNAVAILABLE');

        $result = $this->service->evaluate($target, [$unavailable]);

        self::assertSame(50, $result['score']);
        self::assertSame(0, $result['adjustment']);
        self::assertSame(0, $result['evidence']);
    }

    public function testDissimilarNegativeOfferDoesNotPenalizeTarget(): void
    {
        $target = $this->job('PHP Symfony Developer', 'PHP Symfony API Platform');
        $negative = $this->decision($this->job('Java Spring Developer', 'Java Spring Boot Kafka'), 'IGNORED_NOT_MATCH');

        $result = $this->service->evaluate($target, [$negative]);

        self::assertSame(50, $result['score']);
        self::assertSame(0, $result['adjustment']);
    }

    public function testBatchEvaluationKeepsIndependentResultsForMultipleTargets(): void
    {
        $phpTarget = $this->job('PHP Symfony Developer', 'PHP Symfony API Platform');
        $javaTarget = $this->job('Java Spring Developer', 'Java Spring Boot Kafka');
        $applications = [
            $this->decision($this->job('Senior PHP Symfony Engineer', 'PHP Symfony API'), 'SUBMITTED'),
            $this->decision($this->job('Java Spring Backend', 'Java Spring Boot'), 'IGNORED_NOT_MATCH'),
        ];

        $results = $this->service->evaluateMany([$phpTarget, $javaTarget], $applications);

        self::assertGreaterThan(50, $results[spl_object_id($phpTarget)]['score']);
        self::assertLessThan(50, $results[spl_object_id($javaTarget)]['score']);
        self::assertSame(1, $results[spl_object_id($phpTarget)]['evidence']);
        self::assertSame(1, $results[spl_object_id($javaTarget)]['evidence']);
    }

    private function decision(JobOffer $job, string $status): Application
    {
        return (new Application($job))->fill(['status' => $status]);
    }

    private function job(string $title, string $description): JobOffer
    {
        return (new JobOffer())->fill([
            'title' => $title,
            'description' => $description,
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'source' => 'Example',
        ]);
    }
}
