<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\JobOffer;
use PHPUnit\Framework\TestCase;

final class JobOfferSourceIdentityTest extends TestCase
{
    public function testSourceCodeIsNormalizedAndSerialized(): void
    {
        $job = (new JobOffer())->fill([
            'source' => 'Arbeitnow France',
            'sourceCode' => ' ARBEITNOW ',
            'externalId' => 'offer-123',
            'title' => 'Développeur Symfony',
            'description' => 'PHP Symfony',
        ]);

        self::assertSame('arbeitnow', $job->getSourceCode());
        self::assertSame('offer-123', $job->getExternalId());
        self::assertSame('Arbeitnow France', $job->getSource());
        self::assertSame('arbeitnow', $job->toArray()['sourceCode']);
    }

    public function testManualOfferMayHaveNoConnectorCode(): void
    {
        $job = (new JobOffer())->fill([
            'source' => 'Manuel',
            'title' => 'Mission Symfony',
            'description' => 'Mission ajoutée manuellement.',
        ]);

        self::assertNull($job->getSourceCode());
        self::assertNull($job->getExternalId());
    }
}
