<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CustomScraperSource;
use App\Service\CustomScraperSearchPlanner;
use PHPUnit\Framework\TestCase;

final class CustomScraperSearchPlannerTest extends TestCase
{
    public function testFallsBackToListingUrlWithoutKeywordConfiguration(): void
    {
        $source = new CustomScraperSource('Example Jobs', 'https://jobs.example.com/offres');

        self::assertSame(
            [['keyword' => null, 'url' => 'https://jobs.example.com/offres']],
            (new CustomScraperSearchPlanner())->plan($source),
        );
    }

    public function testBuildsOneEncodedSearchUrlPerUniqueKeyword(): void
    {
        $source = (new CustomScraperSource('Example Jobs', 'https://jobs.example.com/offres'))->fill([
            'searchUrlTemplate' => 'https://jobs.example.com/recherche?q={keyword}&sort=date',
            'searchKeywords' => [' PHP ', 'Symfony', 'Vue 3', 'php', '', 'React.js'],
        ]);

        self::assertSame(
            [
                ['keyword' => 'PHP', 'url' => 'https://jobs.example.com/recherche?q=PHP&sort=date'],
                ['keyword' => 'Symfony', 'url' => 'https://jobs.example.com/recherche?q=Symfony&sort=date'],
                ['keyword' => 'Vue 3', 'url' => 'https://jobs.example.com/recherche?q=Vue%203&sort=date'],
                ['keyword' => 'React.js', 'url' => 'https://jobs.example.com/recherche?q=React.js&sort=date'],
            ],
            (new CustomScraperSearchPlanner())->plan($source),
        );

        $configuration = $source->toArray();
        self::assertSame('https://jobs.example.com/recherche?q={keyword}&sort=date', $configuration['searchUrlTemplate']);
        self::assertSame(['PHP', 'Symfony', 'Vue 3', 'React.js'], $configuration['searchKeywords']);
    }

    public function testSearchTemplateMustStayOnSourceDomain(): void
    {
        $source = new CustomScraperSource('Example Jobs', 'https://jobs.example.com/offres');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('même domaine');
        $source->fill([
            'searchUrlTemplate' => 'https://other.example.com/jobs?q={keyword}',
            'searchKeywords' => ['PHP'],
        ]);
    }

    public function testSearchKeywordsRequireKeywordTemplate(): void
    {
        $source = new CustomScraperSource('Example Jobs', 'https://jobs.example.com/offres');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('{keyword}');
        $source->fill(['searchKeywords' => ['PHP', 'Symfony']]);
    }

    public function testOnlyKeywordPlaceholderIsAccepted(): void
    {
        $source = new CustomScraperSource('Example Jobs', 'https://jobs.example.com/offres');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Seul le placeholder {keyword}');
        $source->fill([
            'searchUrlTemplate' => 'https://jobs.example.com/jobs?q={keyword}&page={page}',
            'searchKeywords' => ['PHP'],
        ]);
    }
}
