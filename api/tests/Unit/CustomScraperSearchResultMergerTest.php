<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\CustomScraperSearchResultMerger;
use PHPUnit\Framework\TestCase;

final class CustomScraperSearchResultMergerTest extends TestCase
{
    public function testDuplicateAcrossKeywordsKeepsRichestCandidateAndKeywordProvenance(): void
    {
        $result = (new CustomScraperSearchResultMerger())->merge([
            [
                'keyword' => 'PHP',
                'candidates' => [[
                    'externalId' => 'job-123',
                    'sourceUrl' => 'https://jobs.example.com/job/123',
                    'title' => 'Développeur PHP',
                    'description' => 'Courte description',
                    'company' => 'Acme',
                    'rawData' => ['parser' => 'fixture'],
                ]],
            ],
            [
                'keyword' => 'Symfony',
                'candidates' => [[
                    'externalId' => 'job-123',
                    'sourceUrl' => 'https://jobs.example.com/job/123',
                    'title' => 'Développeur PHP / Symfony',
                    'description' => str_repeat('Description détaillée Symfony. ', 10),
                    'company' => 'Acme',
                    'location' => 'Paris',
                    'contractType' => 'CDI',
                    'rawData' => ['parser' => 'fixture-rich'],
                ]],
            ],
        ]);

        self::assertSame(2, $result['rawCount']);
        self::assertSame(1, $result['duplicateCount']);
        self::assertCount(1, $result['candidates']);
        $candidate = $result['candidates'][0];
        self::assertSame('Développeur PHP / Symfony', $candidate['title']);
        self::assertSame('fixture-rich', $candidate['rawData']['parser']);
        self::assertSame(['PHP', 'Symfony'], $candidate['rawData']['discoveredByKeywords']);
    }

    public function testUrlAndTitleProvideFallbackIdentityWhenExternalIdIsMissing(): void
    {
        $candidate = [
            'sourceUrl' => 'https://jobs.example.com/job/no-id',
            'title' => 'Frontend React',
            'description' => 'Mission React',
        ];

        $result = (new CustomScraperSearchResultMerger())->merge([
            ['keyword' => 'React.js', 'candidates' => [$candidate]],
            ['keyword' => 'JavaScript', 'candidates' => [$candidate]],
        ]);

        self::assertSame(2, $result['rawCount']);
        self::assertSame(1, $result['duplicateCount']);
        self::assertCount(1, $result['candidates']);
        self::assertSame(
            ['React.js', 'JavaScript'],
            $result['candidates'][0]['rawData']['discoveredByKeywords'],
        );
    }

    public function testDistinctCandidatesRemainDistinctAndFallbackSearchAddsNoFakeKeyword(): void
    {
        $result = (new CustomScraperSearchResultMerger())->merge([
            [
                'keyword' => null,
                'candidates' => [
                    ['externalId' => '1', 'title' => 'PHP'],
                    ['externalId' => '2', 'title' => 'Symfony'],
                ],
            ],
        ]);

        self::assertSame(2, $result['rawCount']);
        self::assertSame(0, $result['duplicateCount']);
        self::assertCount(2, $result['candidates']);
        self::assertSame([], $result['candidates'][0]['rawData']['discoveredByKeywords']);
        self::assertSame([], $result['candidates'][1]['rawData']['discoveredByKeywords']);
    }

    public function testExistingKeywordProvenanceIsMergedFromEveryDuplicateCandidate(): void
    {
        $result = (new CustomScraperSearchResultMerger())->merge([
            [
                'keyword' => 'Symfony',
                'candidates' => [[
                    'externalId' => 'job-1',
                    'title' => 'Symfony',
                    'rawData' => ['discoveredByKeywords' => ['PHP', 'PHP']],
                ]],
            ],
            [
                'keyword' => 'React.js',
                'candidates' => [[
                    'externalId' => 'job-1',
                    'title' => 'Symfony',
                    'rawData' => ['discoveredByKeywords' => ['Vue.js']],
                ]],
            ],
        ]);

        self::assertSame(
            ['PHP', 'Symfony', 'Vue.js', 'React.js'],
            $result['candidates'][0]['rawData']['discoveredByKeywords'],
        );
    }
}
