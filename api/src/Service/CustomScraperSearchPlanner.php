<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomScraperSource;

final class CustomScraperSearchPlanner
{
    /**
     * @return list<array{keyword: ?string, url: string}>
     */
    public function plan(CustomScraperSource $source): array
    {
        $configuration = $source->toArray();
        $listingUrl = (string) $configuration['listingUrl'];
        $template = is_string($configuration['searchUrlTemplate'] ?? null)
            ? trim((string) $configuration['searchUrlTemplate'])
            : '';
        $keywords = is_array($configuration['searchKeywords'] ?? null)
            ? $configuration['searchKeywords']
            : [];

        if ($template === '' || $keywords === []) {
            return [['keyword' => null, 'url' => $listingUrl]];
        }

        $plan = [];
        $seenUrls = [];
        foreach ($keywords as $keyword) {
            if (!is_string($keyword) || trim($keyword) === '') {
                continue;
            }

            $normalizedKeyword = trim($keyword);
            $url = str_replace('{keyword}', rawurlencode($normalizedKeyword), $template);
            if (isset($seenUrls[$url])) {
                continue;
            }

            $seenUrls[$url] = true;
            $plan[] = [
                'keyword' => $normalizedKeyword,
                'url' => $url,
            ];
        }

        return $plan === [] ? [['keyword' => null, 'url' => $listingUrl]] : $plan;
    }
}
