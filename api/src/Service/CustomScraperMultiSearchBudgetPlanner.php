<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomScraperSource;

final class CustomScraperMultiSearchBudgetPlanner
{
    public const HARD_MAX_TOTAL_LISTING_PAGES = 10;

    public function __construct(private CustomScraperSearchPlanner $searchPlanner)
    {
    }

    /**
     * @return array{
     *     searches: list<array{keyword: ?string, url: string, pageLimit: int}>,
     *     maxPagesPerSearch: int,
     *     requestedMaxListingRequests: int,
     *     globalPageBudget: int,
     *     budgetLimited: bool
     * }
     */
    public function plan(CustomScraperSource $source): array
    {
        $configuration = $source->toArray();
        $searches = $this->searchPlanner->plan($source);
        $maxPagesPerSearch = min(
            self::HARD_MAX_TOTAL_LISTING_PAGES,
            max(1, (int) ($configuration['maxPages'] ?? 1)),
        );
        $requestedMaxListingRequests = count($searches) * $maxPagesPerSearch;
        $globalPageBudget = min(self::HARD_MAX_TOTAL_LISTING_PAGES, $requestedMaxListingRequests);

        $allocations = array_map(
            static fn (array $search): array => [
                ...$search,
                'pageLimit' => 0,
            ],
            $searches,
        );

        $allocated = 0;
        while ($allocated < $globalPageBudget) {
            $progress = false;
            foreach ($allocations as $index => $allocation) {
                if ($allocated >= $globalPageBudget) {
                    break;
                }
                if ($allocation['pageLimit'] >= $maxPagesPerSearch) {
                    continue;
                }

                ++$allocations[$index]['pageLimit'];
                ++$allocated;
                $progress = true;
            }

            if (!$progress) {
                break;
            }
        }

        return [
            'searches' => $allocations,
            'maxPagesPerSearch' => $maxPagesPerSearch,
            'requestedMaxListingRequests' => $requestedMaxListingRequests,
            'globalPageBudget' => $globalPageBudget,
            'budgetLimited' => $requestedMaxListingRequests > $globalPageBudget,
        ];
    }
}
