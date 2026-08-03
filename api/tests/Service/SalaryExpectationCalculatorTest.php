<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\UserSettings;
use App\Service\SalaryExpectationCalculator;
use PHPUnit\Framework\TestCase;

final class SalaryExpectationCalculatorTest extends TestCase
{
    private UserSettings $settings;
    private SalaryExpectationCalculator $calculator;

    protected function setUp(): void
    {
        $this->settings = (new UserSettings())->fill(['minimumCdiSalary' => 35_000]);
        $this->calculator = new SalaryExpectationCalculator();
    }

    public function testCdiUsesMaximumUpToFiftyThousand(): void
    {
        self::assertSame(45_000, $this->calculator->calculate('CDI', 40_000, 45_000, $this->settings)['proposed']);
    }

    public function testCdiUsesFiveThousandBelowHigherMaximum(): void
    {
        self::assertSame(60_000, $this->calculator->calculate('CDI', 60_000, 65_000, $this->settings)['proposed']);
        self::assertSame(55_000, $this->calculator->calculate('CDI', 55_000, 60_000, $this->settings)['proposed']);
    }

    public function testLowCdiRangeIsRejected(): void
    {
        $result = $this->calculator->calculate('CDI', 30_000, 34_000, $this->settings);
        self::assertFalse($result['eligible']);
        self::assertNull($result['proposed']);
    }

    public function testCddHasNoAutomaticSalaryRule(): void
    {
        $result = $this->calculator->calculate('CDD', 40_000, 50_000, $this->settings);
        self::assertTrue($result['eligible']);
        self::assertNull($result['proposed']);
    }
}
