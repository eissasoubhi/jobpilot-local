<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\JobOffer;
use App\Service\TargetCompanyName;
use PHPUnit\Framework\TestCase;

final class TargetCompanyNameTest extends TestCase
{
    public function testSourcePlatformIsNotTreatedAsEmployer(): void
    {
        $job = (new JobOffer())->fill([
            'source' => 'Indeed',
            'sourceCode' => 'indeed-assisted',
            'sourceUrl' => 'https://fr.indeed.com/viewjob?jk=123',
            'company' => 'Indeed',
        ]);

        self::assertSame('', TargetCompanyName::resolve($job));
    }

    public function testClientNameIsPreferredOverPlatformCompany(): void
    {
        $job = (new JobOffer())->fill([
            'source' => 'Indeed',
            'sourceCode' => 'indeed-assisted',
            'sourceUrl' => 'https://fr.indeed.com/viewjob?jk=123',
            'company' => 'Indeed',
            'clientName' => 'Proton AG',
        ]);

        self::assertSame('Proton AG', TargetCompanyName::resolve($job));
    }

    public function testExplicitOverrideWinsAndIsCleaned(): void
    {
        $job = (new JobOffer())->fill([
            'source' => 'Indeed',
            'sourceCode' => 'indeed-assisted',
            'company' => 'Indeed',
        ]);

        self::assertSame('Proton', TargetCompanyName::resolve($job, '  <strong>Proton</strong>  '));
    }

    public function testExplicitBlankOverrideSuppressesFallbackCompany(): void
    {
        $job = (new JobOffer())->fill([
            'source' => 'France Travail',
            'sourceCode' => 'france-travail',
            'company' => 'Acme',
        ]);

        self::assertSame('', TargetCompanyName::resolve($job, '   '));
    }
}
