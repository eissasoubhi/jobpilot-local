<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Service\JobOfferActionabilityPolicy;
use PHPUnit\Framework\TestCase;

final class JobOfferActionabilityPolicyTest extends TestCase
{
    private JobOfferActionabilityPolicy $policy;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->policy = new JobOfferActionabilityPolicy();
        $this->now = new \DateTimeImmutable('2026-08-23 21:00:00');
    }

    public function testOfferPublished29DaysAgoRemainsActionable(): void
    {
        self::assertTrue($this->policy->isWithinReviewWindow(
            $this->jobWithPublishedAt($this->now->sub(new \DateInterval('P29D'))),
            $this->now,
        ));
    }

    public function testOfferPublishedExactly30DaysAgoRemainsActionable(): void
    {
        self::assertTrue($this->policy->isWithinReviewWindow(
            $this->jobWithPublishedAt($this->now->sub(new \DateInterval('P30D'))),
            $this->now,
        ));
    }

    public function testOfferPublished31DaysAgoIsExpiredForNewAction(): void
    {
        self::assertFalse($this->policy->isWithinReviewWindow(
            $this->jobWithPublishedAt($this->now->sub(new \DateInterval('P31D'))),
            $this->now,
        ));
    }

    public function testFallsBackToDiscoveredAtWhenPublicationDateIsUnknown(): void
    {
        $job = new JobOffer();
        $property = new \ReflectionProperty(JobOffer::class, 'discoveredAt');
        $property->setValue($job, $this->now->sub(new \DateInterval('P31D')));

        self::assertFalse($this->policy->isWithinReviewWindow($job, $this->now));
    }

    private function jobWithPublishedAt(\DateTimeImmutable $publishedAt): JobOffer
    {
        return (new JobOffer())->fill(['publishedAt' => $publishedAt->format(DATE_ATOM)]);
    }
}
