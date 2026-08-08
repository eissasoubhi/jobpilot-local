<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\Ai\GeminiJobMatchAnalyzer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

final class GeminiMatchingFingerprintTest extends TestCase
{
    public function testFingerprintChangesWhenOfferOrProfileInputChanges(): void
    {
        $analyzer = new GeminiJobMatchAnalyzer(
            new MockHttpClient(),
            new NullLogger(),
            false,
            '',
            'test-model',
        );

        $base = $analyzer->cacheFingerprint($this->job('PHP Symfony'), $this->settings(['PHP', 'Symfony']));
        $same = $analyzer->cacheFingerprint($this->job('PHP Symfony'), $this->settings(['PHP', 'Symfony']));
        $changedOffer = $analyzer->cacheFingerprint($this->job('PHP Symfony API Platform'), $this->settings(['PHP', 'Symfony']));
        $changedProfile = $analyzer->cacheFingerprint($this->job('PHP Symfony'), $this->settings(['PHP', 'Symfony', 'API Platform']));

        self::assertSame($base, $same);
        self::assertNotSame($base, $changedOffer);
        self::assertNotSame($base, $changedProfile);
    }

    private function job(string $description): JobOffer
    {
        return (new JobOffer())->fill([
            'title' => 'Senior PHP Symfony Developer',
            'description' => $description,
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybrid',
        ]);
    }

    /** @param list<string> $skills */
    private function settings(array $skills): UserSettings
    {
        return (new UserSettings())->fill([
            'targetJobs' => ['Senior PHP Symfony Developer'],
            'skills' => $skills,
            'exclusions' => ['Stage'],
        ]);
    }
}
