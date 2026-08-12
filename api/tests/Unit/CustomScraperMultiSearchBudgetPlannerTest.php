<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CustomScraperSource;
use App\Service\CustomScraperMultiSearchBudgetPlanner;
use App\Service\CustomScraperSearchPlanner;
use PHPUnit\Framework\TestCase;

final class CustomScraperMultiSearchBudgetPlannerTest extends TestCase
{
    public function testConfiguredKeywordsShareTheGlobalListingBudgetFairly(): void
    {
        $source = (new CustomScraperSource('Example Jobs', 'https://jobs.example.com/offres'))->fill([
            'searchUrlTemplate' => 'https://jobs.example.com/recherche?q={keyword}',
            'searchKeywords' => ['PHP', 'Symfony', 'Vue.js', 'React.js'],
            'maxPages' => 5,
        ]);

        $plan = (new CustomScraperMultiSearchBudgetPlanner(new CustomScraperSearchPlanner()))->plan($source);

        self::assertSame(5, $plan['maxPagesPerSearch']);
        self::assertSame(20, $plan['requestedMaxListingRequests']);
        self::assertSame(10, $plan['globalPageBudget']);
        self::assertTrue($plan['budgetLimited']);
        self::assertSame(
            [3, 3, 2, 2],
            array_column($plan['searches'], 'pageLimit'),
        );
        self::assertSame(
            ['PHP', 'Symfony', 'Vue.js', 'React.js'],
            array_column($plan['searches'], 'keyword'),
        );
    }

    public function testSingleFallbackSearchKeepsItsConfiguredPageLimitWhenBelowTheGlobalCap(): void
    {
        $source = (new CustomScraperSource('Example Jobs', 'https://jobs.example.com/offres'))->fill([
            'maxPages' => 5,
        ]);

        $plan = (new CustomScraperMultiSearchBudgetPlanner(new CustomScraperSearchPlanner()))->plan($source);

        self::assertFalse($plan['budgetLimited']);
        self::assertSame(5, $plan['globalPageBudget']);
        self::assertSame(5, $plan['searches'][0]['pageLimit']);
        self::assertNull($plan['searches'][0]['keyword']);
        self::assertSame('https://jobs.example.com/offres', $plan['searches'][0]['url']);
    }

    public function testNoSingleSearchCanExceedTheExistingHardListingCap(): void
    {
        $source = (new CustomScraperSource('Example Jobs', 'https://jobs.example.com/offres'))->fill([
            'maxPages' => 20,
        ]);

        $plan = (new CustomScraperMultiSearchBudgetPlanner(new CustomScraperSearchPlanner()))->plan($source);

        self::assertSame(10, $plan['maxPagesPerSearch']);
        self::assertSame(10, $plan['globalPageBudget']);
        self::assertSame(10, $plan['searches'][0]['pageLimit']);
    }
}
