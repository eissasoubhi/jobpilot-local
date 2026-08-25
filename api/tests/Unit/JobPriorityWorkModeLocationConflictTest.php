<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Service\JobPriorityScoreService;
use PHPUnit\Framework\TestCase;

final class JobPriorityWorkModeLocationConflictTest extends TestCase
{
    public function testHybridPreferenceRanksCompatibleLocationAboveSingleAndDoubleConflicts(): void
    {
        $service = new JobPriorityScoreService();
        $profile = (new CandidateProfile())->fill([
            'preferredLocations' => ['Île-de-France'],
            'acceptedContracts' => ['CDI'],
            'workModePreference' => 'Hybride',
        ]);

        $hybridParis = $service->evaluate($this->job('Paris 75008', 'Hybride'), $profile);
        $onsiteParis = $service->evaluate($this->job('Paris 75008', 'Sur site'), $profile);
        $hybridLyon = $service->evaluate($this->job('Lyon 69002', 'Hybride'), $profile);
        $onsiteLyon = $service->evaluate($this->job('Lyon 69002', 'Sur site'), $profile);

        self::assertSame(100, $hybridParis['components']['preferences']);
        self::assertSame(78, $onsiteParis['components']['preferences']);
        self::assertSame(78, $hybridLyon['components']['preferences']);
        self::assertSame(57, $onsiteLyon['components']['preferences']);

        self::assertGreaterThan($onsiteParis['score'], $hybridParis['score']);
        self::assertGreaterThan($onsiteLyon['score'], $onsiteParis['score']);
        self::assertGreaterThan($onsiteLyon['score'], $hybridLyon['score']);
    }

    public function testUnknownLocationStaysNeutralInsteadOfBecomingAnImplicitLocationConflict(): void
    {
        $service = new JobPriorityScoreService();
        $profile = (new CandidateProfile())->fill([
            'preferredLocations' => ['Île-de-France'],
            'acceptedContracts' => ['CDI'],
            'workModePreference' => 'Hybride',
        ]);

        $unknownHybrid = $service->evaluate($this->job('', 'Hybride'), $profile);
        $knownOutsideHybrid = $service->evaluate($this->job('Lyon 69002', 'Hybride'), $profile);

        self::assertSame(83, $unknownHybrid['components']['preferences']);
        self::assertSame(78, $knownOutsideHybrid['components']['preferences']);
        self::assertGreaterThan(
            $knownOutsideHybrid['components']['preferences'],
            $unknownHybrid['components']['preferences'],
            'An unknown location must remain neutral in the preference component instead of being treated as a known out-of-IDF conflict.',
        );
    }

    private function job(string $location, string $workMode): JobOffer
    {
        $job = (new JobOffer())->fill([
            'source' => 'Manual',
            'sourceCode' => 'manual',
            'title' => 'Senior PHP Symfony Developer',
            'company' => 'Example',
            'location' => $location,
            'contractType' => 'CDI',
            'workMode' => $workMode,
            'description' => str_repeat('Symfony PHP API React architecture testing. ', 12),
            'publishedAt' => (new \DateTimeImmutable('-12 hours'))->format(DATE_ATOM),
        ]);
        $job->setEvaluation('fr', 80, [], null, null, 'PREPARED', null);

        return $job;
    }
}
