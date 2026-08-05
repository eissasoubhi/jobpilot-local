<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Application\ConnectorPayloadQualityAnalyzer;
use PHPUnit\Framework\TestCase;

final class ConnectorPayloadQualityAnalyzerTest extends TestCase
{
    private ConnectorPayloadQualityAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new ConnectorPayloadQualityAnalyzer();
    }

    public function testEmptyCollectionHasNoArtificialQualityScore(): void
    {
        $quality = $this->analyzer->analyze([]);

        self::assertSame(0, $quality['received']);
        self::assertNull($quality['requiredCompleteness']);
        self::assertNull($quality['recommendedCompleteness']);
        self::assertNull($quality['overallCompleteness']);
        self::assertSame(0, $quality['missingRequiredRecords']);
    }

    public function testCompletePayloadsScoreOneHundredPercent(): void
    {
        $quality = $this->analyzer->analyze([
            $this->completePayload('one'),
            $this->completePayload('two'),
        ]);

        self::assertSame(100.0, $quality['requiredCompleteness']);
        self::assertSame(100.0, $quality['recommendedCompleteness']);
        self::assertSame(100.0, $quality['overallCompleteness']);
        self::assertSame(2, $quality['fields']['company']['present']);
        self::assertSame([], $quality['warnings']);
    }

    public function testMissingRequiredAndRecommendedFieldsAreReported(): void
    {
        $incomplete = $this->completePayload('two');
        $incomplete['externalId'] = ' ';
        $incomplete['description'] = null;
        $incomplete['company'] = '';
        $incomplete['sourceUrl'] = null;
        $incomplete['location'] = '';
        $incomplete['contractType'] = '';
        $incomplete['publishedAt'] = null;

        $quality = $this->analyzer->analyze([
            $this->completePayload('one'),
            $incomplete,
        ]);

        self::assertSame(66.7, $quality['requiredCompleteness']);
        self::assertSame(50.0, $quality['recommendedCompleteness']);
        self::assertSame(59.1, $quality['overallCompleteness']);
        self::assertSame(1, $quality['missingRequiredRecords']);
        self::assertSame(1, $quality['fields']['externalId']['missing']);
        self::assertSame(1, $quality['fields']['description']['missing']);
        self::assertNotEmpty($quality['warnings']);
    }

    /** @return array<string, mixed> */
    private function completePayload(string $id): array
    {
        return [
            'externalId' => $id,
            'title' => 'Senior Symfony Developer',
            'description' => 'Build and maintain a production Symfony platform.',
            'company' => 'Example Company',
            'sourceUrl' => 'https://jobs.example.test/'.$id,
            'location' => 'Paris',
            'contractType' => 'CDI',
            'publishedAt' => '2026-08-05T10:00:00+00:00',
        ];
    }
}
