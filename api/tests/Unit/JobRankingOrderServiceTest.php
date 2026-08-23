<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\JobOffer;
use App\Service\JobRankingOrderService;
use PHPUnit\Framework\TestCase;

final class JobRankingOrderServiceTest extends TestCase
{
    private JobRankingOrderService $order;

    protected function setUp(): void
    {
        $this->order = new JobRankingOrderService();
    }

    public function testPriorityRemainsThePrimaryOrderingSignal(): void
    {
        $higherPriority = $this->job(20, '2026-08-20T12:00:00+00:00', 2);
        $lowerPriority = $this->job(99, '2026-08-23T12:00:00+00:00', 1);

        self::assertLessThan(0, $this->order->compare($higherPriority, 80, $lowerPriority, 79));
    }

    public function testMatchAndPublicationDateBreakMeaningfulTiesBeforeId(): void
    {
        $betterMatch = $this->job(90, '2026-08-20T12:00:00+00:00', 20);
        $worseMatch = $this->job(80, '2026-08-23T12:00:00+00:00', 1);
        self::assertLessThan(0, $this->order->compare($betterMatch, 80, $worseMatch, 80));

        $newer = $this->job(80, '2026-08-23T12:00:00+00:00', 20);
        $older = $this->job(80, '2026-08-20T12:00:00+00:00', 1);
        self::assertLessThan(0, $this->order->compare($newer, 80, $older, 80));
    }

    public function testPersistentIdIsAStableNeutralFallbackForExactTies(): void
    {
        $firstPersisted = $this->job(80, '2026-08-23T12:00:00+00:00', 10);
        $secondPersisted = $this->job(80, '2026-08-23T12:00:00+00:00', 11);

        self::assertLessThan(0, $this->order->compare($firstPersisted, 80, $secondPersisted, 80));
        self::assertGreaterThan(0, $this->order->compare($secondPersisted, 80, $firstPersisted, 80));
    }

    private function job(int $score, string $publishedAt, int $id): JobOffer
    {
        $job = (new JobOffer())->fill([
            'title' => 'Senior PHP Developer',
            'company' => 'Example',
            'publishedAt' => $publishedAt,
        ]);
        $job->setEvaluation('fr', $score, [], null, null, 'MATCHED', null);

        $property = new \ReflectionProperty(JobOffer::class, 'id');
        $property->setValue($job, $id);

        return $job;
    }
}
