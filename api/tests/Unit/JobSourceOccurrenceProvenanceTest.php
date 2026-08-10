<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\JobOffer;
use App\Entity\JobSourceOccurrence;
use PHPUnit\Framework\TestCase;

final class JobSourceOccurrenceProvenanceTest extends TestCase
{
    public function testOccurrenceExposesOnlyDerivedAssistedPlatformProvenance(): void
    {
        $offer = (new JobOffer())->fill([
            'title' => 'Développeur Symfony',
            'company' => 'Acme',
            'description' => 'Mission Symfony suffisamment détaillée.',
        ]);
        $occurrence = new JobSourceOccurrence($offer, 'gmail', 'Gmail', 'gmail-abc');
        $occurrence->refresh([
            'sourceUrl' => 'https://www.free-work.com/fr/tech-it/job-mission/php/developpeur-symfony',
            'rawData' => [
                'alertPlatform' => 'Free-Work',
                'alertPlatformCode' => 'free-work',
                'gmailMessageId' => 'sensitive-message-id',
                'sender' => 'private@example.com',
            ],
        ], 'PRIMARY', 100);

        $data = $occurrence->toArray();

        self::assertSame('Free-Work via Gmail', $data['sourceName']);
        self::assertSame('Gmail', $data['connectorName']);
        self::assertSame('gmail', $data['sourceCode']);
        self::assertSame('free-work', $data['originPlatformCode']);
        self::assertSame('Free-Work', $data['originPlatformName']);
        self::assertArrayNotHasKey('rawData', $data);
        self::assertArrayNotHasKey('gmailMessageId', $data);
        self::assertArrayNotHasKey('sender', $data);
    }

    public function testTouchCanRestorePlatformProvenanceWithoutReplacingStoredRawData(): void
    {
        $offer = (new JobOffer())->fill([
            'title' => 'Développeur PHP',
            'company' => 'Example',
            'description' => 'Offre PHP.',
        ]);
        $occurrence = new JobSourceOccurrence($offer, 'gmail', 'Gmail', 'gmail-def');
        $occurrence->refresh([
            'rawData' => ['gmailMessageId' => 'kept-internal'],
        ], 'PRIMARY', 100);

        $occurrence->touch([
            'rawData' => [
                'alertPlatform' => 'LesJeudis',
                'alertPlatformCode' => 'lesjeudis',
                'sender' => 'not-exposed@example.com',
            ],
        ]);

        $data = $occurrence->toArray();
        self::assertSame('LesJeudis via Gmail', $data['sourceName']);
        self::assertSame('Gmail', $data['connectorName']);
        self::assertSame('lesjeudis', $data['originPlatformCode']);
        self::assertSame('LesJeudis', $data['originPlatformName']);
        self::assertArrayNotHasKey('rawData', $data);
    }

    public function testNonAssistedOccurrenceKeepsConnectorNameAsDisplayName(): void
    {
        $offer = new JobOffer();
        $occurrence = new JobSourceOccurrence($offer, 'adzuna', 'Adzuna', 'adzuna-1');
        $occurrence->refresh([], 'PRIMARY', 100);

        $data = $occurrence->toArray();
        self::assertSame('Adzuna', $data['sourceName']);
        self::assertSame('Adzuna', $data['connectorName']);
        self::assertNull($data['originPlatformCode']);
        self::assertNull($data['originPlatformName']);
    }
}
