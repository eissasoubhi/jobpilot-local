<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Operations\SchedulerHeartbeatPolicy;
use PHPUnit\Framework\TestCase;

final class SchedulerHeartbeatPolicyTest extends TestCase
{
    public function testHeartbeatIsFreshWithinIntervalAndGrace(): void
    {
        self::assertTrue(SchedulerHeartbeatPolicy::isFresh(1_000, 1_360, 300, 60));
        self::assertTrue(SchedulerHeartbeatPolicy::isFresh(1_000, 1_359, 300, 60));
    }

    public function testHeartbeatBecomesStaleAfterIntervalAndGrace(): void
    {
        self::assertFalse(SchedulerHeartbeatPolicy::isFresh(1_000, 1_361, 300, 60));
    }

    public function testFutureOrMissingHeartbeatIsNotFresh(): void
    {
        self::assertFalse(SchedulerHeartbeatPolicy::isFresh(0, 1_000, 300, 60));
        self::assertFalse(SchedulerHeartbeatPolicy::isFresh(1_001, 1_000, 300, 60));
    }

    public function testInvalidConfigurationIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SchedulerHeartbeatPolicy::maxAgeSeconds(0, 60);
    }

    public function testNegativeGraceIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SchedulerHeartbeatPolicy::maxAgeSeconds(300, -1);
    }
}
