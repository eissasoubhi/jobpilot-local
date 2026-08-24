<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Service\JobPriorityScoreService;
use PHPUnit\Framework\TestCase;

final class JobPriorityFranceLocationTest extends TestCase
{
    public function testFranceWidePreferenceRewardsExplicitFranceAndPostalEvidenceButKeepsUnclassifiedCityNeutral(): void
    {
        $service = new JobPriorityScoreService();
        $profile = (new CandidateProfile())->fill([
            'preferredLocations' => ['France entière'],
            'acceptedContracts' => ['CDI'],
            'workModePreference' => 'Hybride',
            'desiredSalary' => 50000,
        ]);

        $explicitFrance = $service->evaluate($this->job('Lyon, France'), $profile);
        $postalFrance = $service->evaluate($this->job('Lyon (69002)'), $profile);
        $unclassifiedCity = $service->evaluate($this->job('Lyon'), $profile);

        self::assertSame(100, $explicitFrance['components']['preferences']);
        self::assertSame($explicitFrance['components']['preferences'], $postalFrance['components']['preferences']);
        self::assertSame(83, $unclassifiedCity['components']['preferences']);
        self::assertGreaterThan($unclassifiedCity['components']['preferences'], $explicitFrance['components']['preferences']);
    }

    public function testFranceWidePreferenceRecognizesCorsicaAndOverseasPostalCodes(): void
    {
        $service = new JobPriorityScoreService();
        $profile = (new CandidateProfile())->fill([
            'preferredLocations' => ['France entière'],
            'acceptedContracts' => ['CDI'],
            'workModePreference' => 'Hybride',
        ]);

        self::assertSame(100, $service->evaluate($this->job('Ajaccio 20000'), $profile)['components']['preferences']);
        self::assertSame(100, $service->evaluate($this->job('Pointe-à-Pitre 97110'), $profile)['components']['preferences']);
    }

    private function job(string $location): JobOffer
    {
        $job = (new JobOffer())->fill([
            'source' => 'Manual',
            'sourceCode' => 'manual',
            'title' => 'Senior PHP Symfony Developer',
            'company' => 'Example',
            'location' => $location,
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'description' => str_repeat('Symfony PHP API React architecture testing. ', 12),
            'publishedAt' => (new \DateTimeImmutable('-12 hours'))->format(DATE_ATOM),
            'salaryMin' => 50000,
            'salaryMax' => 50000,
        ]);
        $job->setEvaluation('fr', 80, [], null, 50000, 'PREPARED', null);

        return $job;
    }
}
