<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\MatchingScoreService;
use PHPUnit\Framework\TestCase;

final class MatchingScoreServiceLocationNeutralityTest extends TestCase
{
    public function testFallbackMatchingDoesNotAwardUnexplainedLocationPoints(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'React Developer',
            'description' => 'Build product interfaces.',
            'company' => 'Example',
            'location' => 'Unknown location',
            'contractType' => 'CDI',
        ]);
        $settings = (new UserSettings())->fill([
            'targetJobs' => ['React Developer'],
            'skills' => [],
        ]);

        $result = (new MatchingScoreService())->evaluate($job, $settings);

        // 35 title + 7 neutral seniority + 5 accepted contract + 0 freshness.
        // Location belongs to candidate-aware priority scoring; a fixed bonus here
        // makes every offer look eight points stronger regardless of actual fit.
        self::assertSame(47, $result['score']);
        self::assertContains('Compatibilité intitulé : 35/35', $result['reasons']);
        self::assertFalse($result['hardRejected']);
    }
}
