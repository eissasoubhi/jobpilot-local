<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Service\SearchPreferenceMatcher;
use PHPUnit\Framework\TestCase;

final class SearchPreferenceMatcherTest extends TestCase
{
    public function testFreelanceFamilyRejectsCdiAndAcceptsFreelancePortageAndSubcontracting(): void
    {
        $profile = (new CandidateProfile())->fill([
            'acceptedContracts' => ['Freelance', 'Portage salarial', 'Sous-traitance'],
            'workModePreference' => 'Aucune préférence',
        ]);
        $service = new SearchPreferenceMatcher();

        foreach (['Mission freelance', 'Portage salarial', 'Sous-traitance', 'Indépendant', 'B2B contractor'] as $contract) {
            $job = (new JobOffer())->fill(['contractType' => $contract, 'workMode' => 'Hybride']);
            self::assertTrue($service->evaluate($job, $profile)['eligible'], $contract);
            self::assertTrue($service->isFreelanceContract($contract), $contract);
        }

        $cdi = (new JobOffer())->fill(['contractType' => 'CDI', 'workMode' => 'Hybride']);
        self::assertFalse($service->evaluate($cdi, $profile)['eligible']);
        self::assertFalse($service->isFreelanceContract('CDI'));
    }

    public function testFullRemotePreferenceRejectsHybridButKeepsUnknownSourceData(): void
    {
        $profile = (new CandidateProfile())->fill([
            'acceptedContracts' => [],
            'workModePreference' => 'Télétravail uniquement',
        ]);
        $service = new SearchPreferenceMatcher();

        $remote = (new JobOffer())->fill(['contractType' => 'Freelance', 'workMode' => 'Full remote']);
        $hybrid = (new JobOffer())->fill(['contractType' => 'Freelance', 'workMode' => 'Hybride']);
        $unknown = (new JobOffer())->fill(['contractType' => 'Freelance', 'workMode' => '']);

        self::assertTrue($service->evaluate($remote, $profile)['eligible']);
        self::assertFalse($service->evaluate($hybrid, $profile)['eligible']);
        self::assertTrue($service->evaluate($unknown, $profile)['eligible']);
    }

    public function testHybridOrRemoteAcceptsBothModes(): void
    {
        $profile = (new CandidateProfile())->fill([
            'acceptedContracts' => ['Freelance', 'CDI'],
            'workModePreference' => 'Hybride ou télétravail',
        ]);
        $service = new SearchPreferenceMatcher();

        $remote = (new JobOffer())->fill(['contractType' => 'CDI', 'workMode' => 'Télétravail']);
        $hybrid = (new JobOffer())->fill(['contractType' => 'Freelance', 'workMode' => 'Hybride']);
        $onsite = (new JobOffer())->fill(['contractType' => 'Freelance', 'workMode' => 'Sur site']);

        self::assertTrue($service->evaluate($remote, $profile)['eligible']);
        self::assertTrue($service->evaluate($hybrid, $profile)['eligible']);
        self::assertFalse($service->evaluate($onsite, $profile)['eligible']);
    }

    public function testPreferenceRejectionCanBeIdentifiedForLaterReevaluation(): void
    {
        $profile = (new CandidateProfile())->fill([
            'acceptedContracts' => ['Freelance'],
            'workModePreference' => 'Aucune préférence',
        ]);
        $job = (new JobOffer())->fill(['contractType' => 'CDI', 'workMode' => 'Hybride']);
        $service = new SearchPreferenceMatcher();
        $evaluation = $service->evaluate($job, $profile);

        self::assertFalse($evaluation['eligible']);
        $job->setEvaluation('fr', 0, $evaluation['reasons'], null, null, 'REJECTED_BY_FILTER', null);

        self::assertTrue($service->isPreferenceRejection($job));
    }
}
