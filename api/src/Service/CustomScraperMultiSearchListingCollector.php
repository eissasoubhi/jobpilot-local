<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericHtmlModeDetector;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobListingExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericPaginationDetector;

final class CustomScraperMultiSearchListingCollector
{
    public function __construct(
        private CustomScraperMultiSearchBudgetPlanner $budgetPlanner,
        private CustomScraperSearchResultMerger $merger,
        private CustomScraperListingPageFetcher $pageFetcher,
        private GenericJobListingExtractor $extractor,
        private GenericPaginationDetector $paginationDetector,
        private GenericHtmlModeDetector $modeDetector,
    ) {
    }

    /** @return array<string, mixed> */
    public function collect(CustomScraperSource $source): array
    {
        $startedAt = microtime(true);
        $data = $source->toArray();
        if (($data['authorizationConfirmed'] ?? false) !== true) {
            throw new \InvalidArgumentException('L’autorisation de collecte doit être confirmée avant d’exécuter les recherches.');
        }
        if (($data['mode'] ?? CustomScraperSource::MODE_AUTO) === CustomScraperSource::MODE_BROWSER) {
            throw new \RuntimeException('Cette source force Browser/Playwright. La collecte HTTP multi-mots-clés ne peut pas être exécutée.');
        }

        $id = $data['id'] ?? null;
        if (!is_int($id)) {
            throw new \InvalidArgumentException('La source personnalisée doit être persistée avant une collecte multi-mots-clés.');
        }

        $domain = strtolower((string) ($data['domain'] ?? ''));
        $sourceName = (string) ($data['name'] ?? $domain);
        $checkedAt = is_string($data['authorizationCheckedAt'] ?? null)
            ? new \DateTimeImmutable((string) $data['authorizationCheckedAt'])
            : new \DateTimeImmutable('today');
        $plan = $this->budgetPlanner->plan($source);
        $policy = new ConnectorPolicy(
            ConnectorComplianceStatus::ALLOWED,
            $checkedAt,
            is_string($data['authorizationReference'] ?? null)
                ? $data['authorizationReference']
                : 'Autorisation confirmée par l’utilisateur.',
            maxRequestsPerSync: $plan['globalPageBudget'],
            dailyQuota: 300,
            minimumDelayMilliseconds: 1_000,
            respectsRobotsTxt: true,
        );
        $connectorCode = 'custom-scraper-'.$id;

        $batches = [];
        $diagnostics = [];
        $networkRequests = 0;
        $globalError = null;
        $requiresBrowser = false;

        foreach ($plan['searches'] as $search) {
            $searchStartedAt = microtime(true);
            $keyword = is_string($search['keyword'] ?? null) ? $search['keyword'] : null;
            $initialUrl = (string) ($search['url'] ?? '');
            $pageLimit = max(0, (int) ($search['pageLimit'] ?? 0));
            $pageUrl = $initialUrl;
            $pagesFetched = 0;
            $rawCandidates = [];
            $history = [];
            $visited = [];
            $statusCodes = [];
            $stopReason = $pageLimit === 0 ? 'NO_PAGE_BUDGET' : 'PAGE_LIMIT_REACHED';
            $error = null;
            $recommendedMode = CustomScraperSource::MODE_HTTP;

            while ($pagesFetched < $pageLimit) {
                $normalizedUrl = $this->normalizeVisitedUrl($pageUrl);
                if (isset($visited[$normalizedUrl])) {
                    $stopReason = 'LOOP_DETECTED';
                    break;
                }
                $visited[$normalizedUrl] = true;

                try {
                    $response = $this->pageFetcher->fetch($connectorCode, $pageUrl, $policy);
                } catch (\RuntimeException $exception) {
                    $error = $exception->getMessage();
                    $globalError = $error;
                    $stopReason = 'PAGE_FETCH_ERROR';
                    break;
                }

                ++$pagesFetched;
                $networkRequests += $response->attempts;
                $statusCodes[] = $response->statusCode;

                $analysis = $this->modeDetector->analyze($response->body);
                $pageRecommendedMode = (string) ($analysis['recommendedMode'] ?? CustomScraperSource::MODE_HTTP);
                if ($pagesFetched === 1 || $pageRecommendedMode === CustomScraperSource::MODE_BROWSER) {
                    $recommendedMode = $pageRecommendedMode;
                }
                if ($pageRecommendedMode === CustomScraperSource::MODE_BROWSER) {
                    $requiresBrowser = true;
                    $stopReason = 'BROWSER_REQUIRED';
                    $history[] = [
                        'page' => $pagesFetched,
                        'url' => $response->url,
                        'statusCode' => $response->statusCode,
                        'nextUrl' => null,
                        'strategy' => null,
                        'confidence' => null,
                    ];
                    break;
                }

                foreach ($this->extractor->extract($response->body, $response->url, $sourceName) as $candidate) {
                    $rawCandidates[] = $candidate;
                }

                $pagination = $this->paginationDetector->detect($response->body, $response->url);
                $nextUrl = is_string($pagination['nextUrl'] ?? null) ? $pagination['nextUrl'] : null;
                $history[] = [
                    'page' => $pagesFetched,
                    'url' => $response->url,
                    'statusCode' => $response->statusCode,
                    'nextUrl' => $nextUrl,
                    'strategy' => $pagination['strategy'] ?? null,
                    'confidence' => $pagination['confidence'] ?? null,
                ];

                if ($nextUrl === null) {
                    $stopReason = 'NO_NEXT_PAGE';
                    break;
                }
                if (!$this->isSameDomainHttpsUrl($nextUrl, $domain)) {
                    $stopReason = 'UNSAFE_NEXT_PAGE';
                    break;
                }
                if (isset($visited[$this->normalizeVisitedUrl($nextUrl)])) {
                    $stopReason = 'LOOP_DETECTED';
                    break;
                }
                if ($pagesFetched >= $pageLimit) {
                    $stopReason = 'PAGE_LIMIT_REACHED';
                    break;
                }

                $pageUrl = $nextUrl;
            }

            $batches[] = [
                'keyword' => $keyword,
                'candidates' => $rawCandidates,
            ];
            $diagnostics[] = [
                'keyword' => $keyword,
                'requestedUrl' => $initialUrl,
                'pageLimit' => $pageLimit,
                'pagesFetched' => $pagesFetched,
                'rawCandidateCount' => count($rawCandidates),
                'recommendedMode' => $recommendedMode,
                'statusCodes' => $statusCodes,
                'lastStatusCode' => $statusCodes === [] ? null : $statusCodes[array_key_last($statusCodes)],
                'durationMs' => max(0, (int) round((microtime(true) - $searchStartedAt) * 1_000)),
                'stopReason' => $stopReason,
                'error' => $error,
                'history' => $history,
            ];

            // A transport/access failure or an explicit browser requirement is source-wide.
            // Stop before issuing requests for another keyword instead of probing around a block.
            if ($globalError !== null || $requiresBrowser) {
                break;
            }
        }

        $merged = $this->merger->merge($batches);

        return [
            'searchCount' => count($plan['searches']),
            'executedSearchCount' => count($diagnostics),
            'requestedMaxListingRequests' => $plan['requestedMaxListingRequests'],
            'globalPageBudget' => $plan['globalPageBudget'],
            'budgetLimited' => $plan['budgetLimited'],
            'networkRequests' => $networkRequests,
            'durationMs' => max(0, (int) round((microtime(true) - $startedAt) * 1_000)),
            'rawCandidateCount' => $merged['rawCount'],
            'duplicateCount' => $merged['duplicateCount'],
            'candidateCount' => count($merged['candidates']),
            'requiresBrowser' => $requiresBrowser,
            'stoppedEarly' => $globalError !== null || $requiresBrowser,
            'globalError' => $globalError,
            'diagnostics' => $diagnostics,
            'candidates' => $merged['candidates'],
        ];
    }

    private function isSameDomainHttpsUrl(string $url, string $domain): bool
    {
        $parts = parse_url(trim($url));

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === $domain;
    }

    private function normalizeVisitedUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$path.$query;
    }
}
