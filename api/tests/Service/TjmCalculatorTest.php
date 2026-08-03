<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\UserSettings;
use App\Service\TjmCalculator;
use PHPUnit\Framework\TestCase;

final class TjmCalculatorTest extends TestCase
{
    private TjmCalculator $calculator;
    private UserSettings $settings;

    protected function setUp(): void
    {
        $this->calculator = new TjmCalculator();
        $this->settings = (new UserSettings())->fill([
            'defaultIdfTjm' => 500,
            'defaultOutsideIdfTjm' => 480,
            'defaultRemoteTjm' => 480,
            'minimumFreelanceTjm' => 300,
            'maximumTjm' => 520,
        ]);
    }

    public function testRangeAtOrBelowFiveHundredUsesMaximum(): void
    {
        self::assertSame(480, $this->calculator->calculate(null, 400, 480, 'Paris', 'Hybride', $this->settings));
        self::assertSame(500, $this->calculator->calculate(null, 450, 500, 'Paris', 'Hybride', $this->settings));
    }

    public function testRangeAboveFiveHundredUsesMidpointCappedAtFiveTwenty(): void
    {
        self::assertSame(510, $this->calculator->calculate(null, 500, 520, 'Paris', 'Hybride', $this->settings));
        self::assertSame(520, $this->calculator->calculate(null, 480, 600, 'Paris', 'Hybride', $this->settings));
        self::assertSame(520, $this->calculator->calculate(null, 550, 700, 'Paris', 'Hybride', $this->settings));
    }

    public function testFixedRateIsCappedAndMinimumIsEnforced(): void
    {
        self::assertSame(520, $this->calculator->calculate(600, null, null, 'Paris', 'Hybride', $this->settings));
        self::assertNull($this->calculator->calculate(299, null, null, 'Paris', 'Hybride', $this->settings));
    }

    public function testDefaultsFollowLocationEvenForFullRemote(): void
    {
        self::assertSame(500, $this->calculator->calculate(null, null, null, 'Cergy, Île-de-France', 'Hybride', $this->settings));
        self::assertSame(500, $this->calculator->calculate(null, null, null, 'Paris', 'Full remote', $this->settings));
        self::assertSame(480, $this->calculator->calculate(null, null, null, 'Lyon', 'Full remote', $this->settings));
        self::assertSame(480, $this->calculator->calculate(null, null, null, '', 'Full remote', $this->settings));
    }

    public function testOptionalAnswerReturnsNullWithoutAdvertisedRate(): void
    {
        self::assertNull($this->calculator->calculate(null, null, null, 'Paris', 'Hybride', $this->settings, false));
    }

    public function testInvalidRangeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calculator->calculate(null, 600, 500, 'Paris', 'Hybride', $this->settings);
    }
}
