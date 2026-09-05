<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Service\SearchPreferenceMatcher;
use PHPUnit\Framework\TestCase;

final class SearchPreferenceMatcherTest extends TestCase
{
    private SearchPreferenceMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new SearchPreferenceMatcher();
    }

    public function testFreelanceProfileRejectsCdiAndAcceptsPortageAndSubcontracting(): void
    {
        $profile = (new CandidateProfile())->fill([
            'acceptedContracts' => ['Freelance', 'Portage salarial', 'Sous-traitance'],
            'workModePreference' => 'Aucune préférence',
        ]);

        self::assertFalse($this->matcher->evaluate($this->job('CDI', 'Hybride'), $profile)['eligible']);
        self::assertTrue($this->matcher->evaluate($this->job('Freelance', 'Hybride'), $profile)['eligible']);
        self::assertTrue($this->matcher->evaluate($this->job('Portage salarial', 'Hybride'), $profile)['eligible']);
        self::assertTrue($this->matcher->evaluate($this->job('Sous-traitance', 'Hybride'), $profile)['eligible']);
    }

    public function testRemoteOnlyRejectsOnsiteAndAcceptsRemote(): void
    {
        $profile = (new CandidateProfile())->fill([
            'acceptedContracts' => [],
            'workModePreference' => 'Télétravail uniquement',
        ]);

        self::assertTrue($this->matcher->evaluate($this->job('Freelance', '100% remote'), $profile)['eligible']);
        self::assertFalse($this->matcher->evaluate($this->job('Freelance', 'Sur site'), $profile)['eligible']);
    }

    public function testHybridOrRemoteAcceptsBoth(): void
    {
        $profile = (new CandidateProfile())->fill([
            'acceptedContracts' => [],
            'workModePreference' => 'Hybride ou télétravail',
        ]);

        self::assertTrue($this->matcher->evaluate($this->job('Freelance', 'Hybride'), $profile)['eligible']);
        self::assertTrue($this->matcher->evaluate($this->job('Freelance', 'Remote'), $profile)['eligible']);
        self::assertFalse($this->matcher->evaluate($this->job('Freelance', 'Présentiel'), $profile)['eligible']);
    }

    public function testUnknownWorkModeDoesNotSilentlyRejectAnOffer(): void
    {
        $profile = (new CandidateProfile())->fill([
            'acceptedContracts' => ['Freelance'],
            'workModePreference' => 'Télétravail uniquement',
        ]);

        self::assertTrue($this->matcher->evaluate($this->job('Freelance', ''), $profile)['eligible']);
    }

    private function job(string $contractType, string $workMode): JobOffer
    {
        return (new JobOffer())->fill([
            'source' => 'Test',
            'title' => 'Senior PHP Symfony',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => $contractType,
            'workMode' => $workMode,
            'description' => 'Mission PHP Symfony',
        ]);
    }
}
