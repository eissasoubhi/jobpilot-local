<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Application\CustomScraperBrowserRenderPolicy;
use PHPUnit\Framework\TestCase;

final class CustomScraperBrowserRenderPolicyTest extends TestCase
{
    public function testAutoUsesBrowserOnlyWhenRecommendedAndAllGatesPass(): void
    {
        $policy = new CustomScraperBrowserRenderPolicy();

        self::assertTrue($policy->shouldRender('AUTO', 'BROWSER', true, true, true));
        self::assertFalse($policy->shouldRender('AUTO', 'HTTP', true, true, true));
    }

    public function testForcedHttpNeverFallsBackToBrowser(): void
    {
        self::assertFalse((new CustomScraperBrowserRenderPolicy())->shouldRender(
            'HTTP', 'BROWSER', true, true, true,
        ));
    }

    public function testForcedBrowserStillRequiresWorkerAuthorizationAndRobots(): void
    {
        $policy = new CustomScraperBrowserRenderPolicy();

        self::assertTrue($policy->shouldRender('BROWSER', 'HTTP', true, true, true));
        self::assertFalse($policy->shouldRender('BROWSER', 'HTTP', false, true, true));
        self::assertFalse($policy->shouldRender('BROWSER', 'HTTP', true, false, true));
        self::assertFalse($policy->shouldRender('BROWSER', 'HTTP', true, true, false));
    }
}
