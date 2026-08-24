<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\JobOffer;
use App\Entity\JobOfferMatchingScoreState;
use App\Service\MatchingScoreVersion;
use PHPUnit\Framework\TestCase;

final class JobOfferMatchingScoreStateTest extends TestCase
{
    public function testVersionCanBeUpgradedWithoutTouchingTheJobWorkflow(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Senior PHP Symfony React Developer',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybrid',
            'description' => 'PHP Symfony React.',
            'status' => 'PREPARED',
        ]);
        $job->refreshMatchingScore(63, ['Historique : bonus localisation +8']);

        $state = new JobOfferMatchingScoreState($job, 0);
        $state->markVersion(MatchingScoreVersion::CURRENT);

        self::assertSame(MatchingScoreVersion::CURRENT, $state->getVersion());
        self::assertSame(63, $job->getScore());
        self::assertSame('PREPARED', $job->getStatus());
        self::assertSame(['Historique : bonus localisation +8'], $job->getScoreReasons());
    }

    public function testNegativeVersionsAreClampedToZero(): void
    {
        $state = new JobOfferMatchingScoreState(new JobOffer(), -12);

        self::assertSame(0, $state->getVersion());
    }
}
