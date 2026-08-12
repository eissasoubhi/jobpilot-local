<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Application\CustomScraperDetailPriority;
use App\JobDiscovery\Application\CustomScraperOfferQualityEvaluator;
use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericHtmlModeDetector;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobDetailExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobListingExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericPaginationDetector;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingRequest;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingResult;

final class CustomScraperExtractionService
{
    private const HARD_MAX_DETAIL_PREVIEW = 10;
    private const HARD_MAX_SYNC_PAGES = 10;
    private const HARD_MAX_SYNC_DETAILS = 30;

    public function __construct(
        private ControlledHttpScrapingClient $httpClient,
        private GenericHtmlModeDetector $modeDetector,
        private GenericJobListingExtractor $extractor,
        private GenericJobDetailExtractor $detailExtractor,
        private CustomScraperOfferQualityEvaluator $qualityEvaluator,
        private GenericPaginationDetector $paginationDetector,
        private CustomScraperDetailPriority $detailPriority,
        private CustomScraperMultiSearchListingCollector $multiSearchCollector,
    ) {
    }

    /** @return array<string, mixed> */
    public function preview(CustomScraperSource $source): array
    {
        $data = $this->authorizedSourceData($source);
        $detailLimit = min(self::HARD_MAX_DETAIL_PREVIEW, max(0, (int) ($data['maxDetails'] ?? 0)));
        $connectorCode = 'custom-preview-'.substr(hash('sha256', (string) $data['domain'].'|'.(string) $data['listingUrl']), 0, 16);

        return $this->run(
            $data,
            pageLimit: 1,
            detailLimit: $detailLimit,
            connectorCode: $connectorCode,
            dailyQuota: 100,
            targetJobs: [],
            skills: [],
        );
    }

    /**
     * @param list<string> $targetJobs
     * @param list<string> $skills
     * @return array<string, mixed>
     */
    public function collect(CustomScraperSource $source, array $targetJobs = [], array $skills = []): array
    {
        $data = $this->authorizedSourceData($source);
        $id = $data['id'] ?? null;
        if (!is_int($id)) {
            throw new \InvalidArgumentException('La source personnalisée doit être persistée avant une synchronisation automatique.');
        }

        $detailLimit = min(self::HARD_MAX_SYNC_DETAILS, max(0, (int) ($data['maxDetails'] ?? 0)));
        if ($this->hasConfiguredKeywordSearches($data)) {
            return $this->runMultiSearch(
                $source,
                $data,
                detailLimit: $detailLimit,
                connectorCode: 'custom-scraper-'.$id,
                targetJobs: $targetJobs,
                skills: $skills,
            );
        }

        return $this->run(
            $data,
            pageLimit: min(self::HARD_MAX_SYNC_PAGES, max(1, (int) ($data['maxPages'] ?? 1))),
            detailLimit: $detailLimit,
            connectorCode: 'custom-scraper-'.$id,
            dailyQuota: 300,
            targetJobs: $targetJobs,
            skills: $skills,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $targetJobs
     * @param list<string> $skills
     * @return array<string, mixed>
     */
    private function runMultiSearch(
        CustomScraperSource $source,
        array $data,
        int $detailLimit,
        string $connectorCode,
        array $targetJobs,
        array $skills,
    ): array {
        $configuredMode = (string) ($data['mode'] ?? CustomScraperSource::MODE_AUTO);
        if ($configuredMode === CustomScraperSource::MODE_BROWSER) {
            throw new \RuntimeException('Cette source force Browser/Playwright. Le worker navigateur n’est pas encore activé pour l’extraction.');
        }

        $domain = (string) ($data['domain'] ?? '');
        $sourceName = (string) ($data['name'] ?? $domain);
        $listingUrl = (string) ($data['listingUrl'] ?? '');
        $listingCollection = $this->multiSearchCollector->collect($source);
        $candidates = array_values(array_filter(
            is_array($listingCollection['candidates'] ?? null) ? $listingCollection['candidates'] : [],
            static fn (mixed $candidate): bool => is_array($candidate),
        ));
        $requiresBrowser = (bool) ($listingCollection['requiresBrowser'] ?? false);
        $globalError = is_string($listingCollection['globalError'] ?? null)
            ? trim((string) $listingCollection['globalError'])
            : '';

        // Preserve the legacy automatic-sync contract: a failure before extracting any
        // candidate remains a connector failure. Later failures keep partial candidates
        // and are exposed through pagination/search diagnostics.
        if ($globalError !== '' && $candidates === []) {
            throw new \RuntimeException($globalError);
        }

        $recommendedMode = $requiresBrowser
            ? CustomScraperSource::MODE_BROWSER
            : CustomScraperSource::MODE_HTTP;
        $effectiveMode = $configuredMode === CustomScraperSource::MODE_AUTO
            ? $recommendedMode
            : $configuredMode;
        $detailPriorityApplied = $targetJobs !== [] || $skills !== [];
        $detailEnriched = 0;
        $detailError = null;
        $networkRequests = max(0, (int) ($listingCollection['networkRequests'] ?? 0));
        $globalPageBudget = max(1, (int) ($listingCollection['globalPageBudget'] ?? 1));
        $listingUrls = $this->multiSearchListingUrls($listingCollection);

        $checkedAt = is_string($data['authorizationCheckedAt'] ?? null)
            ? new \DateTimeImmutable((string) $data['authorizationCheckedAt'])
            : new \DateTimeImmutable('today');
        $detailPolicy = new ConnectorPolicy(
            ConnectorComplianceStatus::ALLOWED,
            $checkedAt,
            is_string($data['authorizationReference'] ?? null)
                ? $data['authorizationReference']
                : 'Autorisation confirmée par l’utilisateur.',
            maxRequestsPerSync: $globalPageBudget + $detailLimit,
            dailyQuota: 300,
            minimumDelayMilliseconds: 1_000,
            respectsRobotsTxt: true,
        );

        // Do not continue probing detail pages after the listing phase explicitly
        // reported that Browser rendering is required for this source.
        if (!$requiresBrowser && $effectiveMode === CustomScraperSource::MODE_HTTP && $detailLimit > 0 && $candidates !== []) {
            foreach ($this->detailPriority->rank($candidates, $targetJobs, $skills) as $index) {
                if ($detailEnriched >= $detailLimit) {
                    break;
                }

                $candidate = $candidates[$index] ?? null;
                if (!is_array($candidate) || !$this->needsDetailFetch($candidate)) {
                    continue;
                }

                $detailUrl = $this->eligibleDetailUrl(
                    (string) ($candidate['sourceUrl'] ?? ''),
                    $domain,
                    $listingUrls,
                );
                if ($detailUrl === null) {
                    continue;
                }

                try {
                    $detailResponse = $this->fetch($connectorCode, $detailUrl, $detailPolicy);
                    $networkRequests += $detailResponse->attempts;
                    $candidates[$index] = $this->detailExtractor->enrich(
                        $detailResponse->body,
                        $candidate,
                        $detailResponse->url,
                        $sourceName,
                    );
                    ++$detailEnriched;
                } catch (\RuntimeException $exception) {
                    $detailError = $exception->getMessage();
                    break;
                }
            }
        }

        $reliableCount = $this->attachQuality($candidates, $domain);
        $searchDiagnostics = is_array($listingCollection['diagnostics'] ?? null)
            ? array_values($listingCollection['diagnostics'])
            : [];
        $pagesFetched = array_sum(array_map(
            static fn (mixed $diagnostic): int => is_array($diagnostic)
                ? max(0, (int) ($diagnostic['pagesFetched'] ?? 0))
                : 0,
            $searchDiagnostics,
        ));
        $loopDetected = $this->searchDiagnosticsContainStopReason($searchDiagnostics, 'LOOP_DETECTED');
        $paginationStopReason = $this->multiSearchStopReason($searchDiagnostics, $requiresBrowser, $globalError);
        $firstStatusCode = $this->firstSearchStatusCode($searchDiagnostics);
        $finalUrl = $this->lastSearchUrl($searchDiagnostics) ?? $listingUrl;

        return [
            'configuredMode' => $configuredMode,
            'recommendedMode' => $recommendedMode,
            'effectiveMode' => $effectiveMode,
            'requiresBrowser' => $requiresBrowser,
            'candidateCount' => count($candidates),
            'reliableCount' => $reliableCount,
            'detailLimit' => $detailLimit,
            'detailEnriched' => $detailEnriched,
            'detailError' => $detailError,
            'detailPriorityApplied' => $detailPriorityApplied,
            'pagination' => [
                'nextUrl' => null,
                'strategy' => 'MULTI_KEYWORD_SEARCH',
                'confidence' => null,
                'pagesFetched' => $pagesFetched,
                'pageLimit' => $globalPageBudget,
                'stopReason' => $paginationStopReason,
                'loopDetected' => $loopDetected,
                'pageError' => $globalError !== '' ? $globalError : null,
                'history' => $this->multiSearchPaginationHistory($searchDiagnostics),
            ],
            'candidates' => $candidates,
            'signals' => [],
            'searches' => [
                'enabled' => true,
                'searchCount' => max(0, (int) ($listingCollection['searchCount'] ?? 0)),
                'executedSearchCount' => max(0, (int) ($listingCollection['executedSearchCount'] ?? 0)),
                'requestedMaxListingRequests' => max(0, (int) ($listingCollection['requestedMaxListingRequests'] ?? 0)),
                'globalPageBudget' => $globalPageBudget,
                'budgetLimited' => (bool) ($listingCollection['budgetLimited'] ?? false),
                'rawCandidateCount' => max(0, (int) ($listingCollection['rawCandidateCount'] ?? 0)),
                'duplicateCount' => max(0, (int) ($listingCollection['duplicateCount'] ?? 0)),
                'durationMs' => max(0, (int) ($listingCollection['durationMs'] ?? 0)),
                'stoppedEarly' => (bool) ($listingCollection['stoppedEarly'] ?? false),
                'globalError' => $globalError !== '' ? $globalError : null,
                'diagnostics' => $searchDiagnostics,
            ],
            'http' => [
                'requestedUrl' => $this->firstSearchRequestedUrl($searchDiagnostics) ?? $listingUrl,
                'finalUrl' => $finalUrl,
                'statusCode' => $firstStatusCode,
                'responseBytes' => 0,
                'networkRequests' => $networkRequests,
                'fromCache' => false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $targetJobs
     * @param list<string> $skills
     * @return array<string, mixed>
     */
    private function run(
        array $data,
        int $pageLimit,
        int $detailLimit,
        string $connectorCode,
        int $dailyQuota,
        array $targetJobs,
        array $skills,
    ): array {
        $configuredMode = (string) ($data['mode'] ?? CustomScraperSource::MODE_AUTO);
        if ($configuredMode === CustomScraperSource::MODE_BROWSER) {
            throw new \RuntimeException('Cette source force Browser/Playwright. Le worker navigateur n’est pas encore activé pour l’extraction.');
        }

        $listingUrl = (string) ($data['listingUrl'] ?? '');
        $domain = (string) ($data['domain'] ?? '');
        $sourceName = (string) ($data['name'] ?? $domain);
        $checkedAt = is_string($data['authorizationCheckedAt'] ?? null)
            ? new \DateTimeImmutable((string) $data['authorizationCheckedAt'])
            : new \DateTimeImmutable('today');
        $policy = new ConnectorPolicy(
            ConnectorComplianceStatus::ALLOWED,
            $checkedAt,
            is_string($data['authorizationReference'] ?? null) ? $data['authorizationReference'] : 'Autorisation confirmée par l’utilisateur.',
            maxRequestsPerSync: $pageLimit + $detailLimit,
            dailyQuota: $dailyQuota,
            minimumDelayMilliseconds: 1_000,
            respectsRobotsTxt: true,
        );

        $pageUrl = $listingUrl;
        $visited = [];
        $candidatesByKey = [];
        $paginationHistory = [];
        $pagesFetched = 0;
        $networkRequests = 0;
        $paginationStopReason = 'NO_NEXT_PAGE';
        $paginationLoopDetected = false;
        $pageError = null;
        $recommendedMode = CustomScraperSource::MODE_HTTP;
        $effectiveMode = $configuredMode === CustomScraperSource::MODE_AUTO
            ? CustomScraperSource::MODE_HTTP
            : $configuredMode;
        $requiresBrowser = false;
        $signals = [];
        $firstResponse = null;
        $lastResponse = null;
        $lastPagination = ['nextUrl' => null, 'strategy' => null, 'confidence' => null];

        while ($pagesFetched < $pageLimit) {
            $normalizedPageUrl = $this->normalizeVisitedUrl($pageUrl);
            if (isset($visited[$normalizedPageUrl])) {
                $paginationLoopDetected = true;
                $paginationStopReason = 'LOOP_DETECTED';
                break;
            }
            $visited[$normalizedPageUrl] = true;

            try {
                $response = $this->fetch($connectorCode, $pageUrl, $policy);
            } catch (\RuntimeException $exception) {
                if ($pagesFetched === 0) {
                    throw $exception;
                }
                $pageError = $exception->getMessage();
                $paginationStopReason = 'PAGE_FETCH_ERROR';
                break;
            }

            $firstResponse ??= $response;
            $lastResponse = $response;
            $networkRequests += $response->attempts;
            ++$pagesFetched;

            $analysis = $this->modeDetector->analyze($response->body);
            if ($pagesFetched === 1) {
                $recommendedMode = (string) ($analysis['recommendedMode'] ?? CustomScraperSource::MODE_HTTP);
                $effectiveMode = $configuredMode === CustomScraperSource::MODE_AUTO
                    ? $recommendedMode
                    : $configuredMode;
                $signals = is_array($analysis['signals'] ?? null) ? $analysis['signals'] : [];
            } elseif ($configuredMode === CustomScraperSource::MODE_AUTO
                && ($analysis['recommendedMode'] ?? CustomScraperSource::MODE_HTTP) === CustomScraperSource::MODE_BROWSER) {
                $requiresBrowser = true;
                $paginationStopReason = 'BROWSER_REQUIRED';
                $lastPagination = ['nextUrl' => null, 'strategy' => null, 'confidence' => null];
                $paginationHistory[] = [
                    'page' => $pagesFetched,
                    'url' => $response->url,
                    'nextUrl' => null,
                    'strategy' => null,
                    'confidence' => null,
                ];
                break;
            }

            if ($effectiveMode !== CustomScraperSource::MODE_HTTP) {
                $requiresBrowser = true;
                $paginationStopReason = 'BROWSER_REQUIRED';
                $lastPagination = ['nextUrl' => null, 'strategy' => null, 'confidence' => null];
                break;
            }

            foreach ($this->extractor->extract($response->body, $response->url, $sourceName) as $candidate) {
                $key = $this->candidateKey($candidate);
                if (!isset($candidatesByKey[$key]) || $this->candidateRichness($candidate) > $this->candidateRichness($candidatesByKey[$key])) {
                    $candidatesByKey[$key] = $candidate;
                }
            }

            $lastPagination = $this->paginationDetector->detect($response->body, $response->url);
            $nextUrl = is_string($lastPagination['nextUrl'] ?? null) ? $lastPagination['nextUrl'] : null;
            $paginationHistory[] = [
                'page' => $pagesFetched,
                'url' => $response->url,
                'nextUrl' => $nextUrl,
                'strategy' => $lastPagination['strategy'] ?? null,
                'confidence' => $lastPagination['confidence'] ?? null,
            ];

            if ($nextUrl === null) {
                $paginationStopReason = 'NO_NEXT_PAGE';
                break;
            }
            if (isset($visited[$this->normalizeVisitedUrl($nextUrl)])) {
                $paginationLoopDetected = true;
                $paginationStopReason = 'LOOP_DETECTED';
                break;
            }
            if ($pagesFetched >= $pageLimit) {
                $paginationStopReason = 'PAGE_LIMIT_REACHED';
                break;
            }

            $pageUrl = $nextUrl;
        }

        $candidates = array_values($candidatesByKey);
        if ($recommendedMode === CustomScraperSource::MODE_BROWSER && $candidates === []) {
            $requiresBrowser = true;
        }

        $detailEnriched = 0;
        $detailError = null;
        $detailPriorityApplied = $targetJobs !== [] || $skills !== [];
        if ($effectiveMode === CustomScraperSource::MODE_HTTP && $detailLimit > 0 && $candidates !== []) {
            foreach ($this->detailPriority->rank($candidates, $targetJobs, $skills) as $index) {
                if ($detailEnriched >= $detailLimit) {
                    break;
                }

                $candidate = $candidates[$index] ?? null;
                if (!is_array($candidate) || !$this->needsDetailFetch($candidate)) {
                    continue;
                }

                $detailUrl = $this->eligibleDetailUrl(
                    (string) ($candidate['sourceUrl'] ?? ''),
                    $domain,
                    array_keys($visited),
                );
                if ($detailUrl === null) {
                    continue;
                }

                try {
                    $detailResponse = $this->fetch($connectorCode, $detailUrl, $policy);
                    $networkRequests += $detailResponse->attempts;
                    $candidates[$index] = $this->detailExtractor->enrich(
                        $detailResponse->body,
                        $candidate,
                        $detailResponse->url,
                        $sourceName,
                    );
                    ++$detailEnriched;
                } catch (\RuntimeException $exception) {
                    $detailError = $exception->getMessage();
                    break;
                }
            }
        }

        $reliableCount = $this->attachQuality($candidates, $domain);

        return [
            'configuredMode' => $configuredMode,
            'recommendedMode' => $recommendedMode,
            'effectiveMode' => $effectiveMode,
            'requiresBrowser' => $requiresBrowser,
            'candidateCount' => count($candidates),
            'reliableCount' => $reliableCount,
            'detailLimit' => $detailLimit,
            'detailEnriched' => $detailEnriched,
            'detailError' => $detailError,
            'detailPriorityApplied' => $detailPriorityApplied,
            'pagination' => [
                'nextUrl' => $lastPagination['nextUrl'] ?? null,
                'strategy' => $lastPagination['strategy'] ?? null,
                'confidence' => $lastPagination['confidence'] ?? null,
                'pagesFetched' => $pagesFetched,
                'pageLimit' => $pageLimit,
                'stopReason' => $paginationStopReason,
                'loopDetected' => $paginationLoopDetected,
                'pageError' => $pageError,
                'history' => $paginationHistory,
            ],
            'candidates' => $candidates,
            'signals' => $signals,
            'http' => [
                'requestedUrl' => $listingUrl,
                'finalUrl' => $lastResponse?->url ?? $listingUrl,
                'statusCode' => $firstResponse?->statusCode ?? 0,
                'responseBytes' => $firstResponse !== null ? strlen($firstResponse->body) : 0,
                'networkRequests' => $networkRequests,
                'fromCache' => $firstResponse?->fromCache ?? false,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function authorizedSourceData(CustomScraperSource $source): array
    {
        $data = $source->toArray();
        if (($data['authorizationConfirmed'] ?? false) !== true) {
            throw new \InvalidArgumentException('L’autorisation de collecte doit être confirmée avant d’extraire des offres.');
        }

        return $data;
    }

    private function fetch(string $connectorCode, string $url, ConnectorPolicy $policy): HttpScrapingResult
    {
        return $this->httpClient->fetch(new HttpScrapingRequest(
            $connectorCode,
            $url,
            $policy,
            timeoutSeconds: 10,
            maxRetries: 0,
            initialBackoffMilliseconds: 0,
            maxResponseBytes: 3_000_000,
        ));
    }

    /** @param array<string, mixed> $candidate */
    private function needsDetailFetch(array $candidate): bool
    {
        $rawData = is_array($candidate['rawData'] ?? null) ? $candidate['rawData'] : [];
        if (($rawData['needsDetailFetch'] ?? false) === true) {
            return true;
        }

        return trim((string) ($candidate['description'] ?? '')) === '';
    }

    /** @param list<string> $listingUrls */
    private function eligibleDetailUrl(string $url, string $domain, array $listingUrls): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== strtolower($domain)) {
            return null;
        }

        if (in_array($this->normalizeVisitedUrl($url), $listingUrls, true)) {
            return null;
        }

        return $url;
    }

    /** @param array<string, mixed> $candidate */
    private function candidateKey(array $candidate): string
    {
        $externalId = trim((string) ($candidate['externalId'] ?? ''));
        if ($externalId !== '') {
            return 'id:'.$externalId;
        }

        return 'url:'.hash('sha256', trim((string) ($candidate['sourceUrl'] ?? '')).'|'.trim((string) ($candidate['title'] ?? '')));
    }

    /** @param array<string, mixed> $candidate */
    private function candidateRichness(array $candidate): int
    {
        $score = mb_strlen(trim((string) ($candidate['description'] ?? '')));
        foreach (['company', 'location', 'contractType', 'workMode', 'publishedAt'] as $field) {
            if (trim((string) ($candidate[$field] ?? '')) !== '') {
                $score += 100;
            }
        }

        return $score;
    }

    /** @param list<array<string, mixed>> $candidates */
    private function attachQuality(array &$candidates, string $domain): int
    {
        $reliableCount = 0;
        foreach ($candidates as $index => $candidate) {
            $quality = $this->qualityEvaluator->evaluate($candidate, $domain);
            $rawData = is_array($candidate['rawData'] ?? null) ? $candidate['rawData'] : [];
            $candidate['rawData'] = [
                ...$rawData,
                'quality' => $quality,
            ];
            $candidates[$index] = $candidate;
            if ($quality['reliable']) {
                ++$reliableCount;
            }
        }

        return $reliableCount;
    }

    /** @param array<string, mixed> $data */
    private function hasConfiguredKeywordSearches(array $data): bool
    {
        return is_string($data['searchUrlTemplate'] ?? null)
            && trim((string) $data['searchUrlTemplate']) !== ''
            && is_array($data['searchKeywords'] ?? null)
            && $data['searchKeywords'] !== [];
    }

    /** @param array<string, mixed> $listingCollection @return list<string> */
    private function multiSearchListingUrls(array $listingCollection): array
    {
        $diagnostics = is_array($listingCollection['diagnostics'] ?? null)
            ? $listingCollection['diagnostics']
            : [];
        $urls = [];

        foreach ($diagnostics as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }
            $requestedUrl = trim((string) ($diagnostic['requestedUrl'] ?? ''));
            if ($requestedUrl !== '') {
                $urls[$this->normalizeVisitedUrl($requestedUrl)] = true;
            }
            foreach (is_array($diagnostic['history'] ?? null) ? $diagnostic['history'] : [] as $page) {
                if (!is_array($page)) {
                    continue;
                }
                $url = trim((string) ($page['url'] ?? ''));
                if ($url !== '') {
                    $urls[$this->normalizeVisitedUrl($url)] = true;
                }
            }
        }

        return array_keys($urls);
    }

    /** @param list<array<string, mixed>> $diagnostics */
    private function searchDiagnosticsContainStopReason(array $diagnostics, string $stopReason): bool
    {
        foreach ($diagnostics as $diagnostic) {
            if (is_array($diagnostic) && ($diagnostic['stopReason'] ?? null) === $stopReason) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string, mixed>> $diagnostics */
    private function multiSearchStopReason(array $diagnostics, bool $requiresBrowser, string $globalError): string
    {
        if ($globalError !== '') {
            return 'PAGE_FETCH_ERROR';
        }
        if ($requiresBrowser) {
            return 'BROWSER_REQUIRED';
        }
        if ($this->searchDiagnosticsContainStopReason($diagnostics, 'LOOP_DETECTED')) {
            return 'LOOP_DETECTED';
        }
        if (count($diagnostics) === 1 && is_string($diagnostics[0]['stopReason'] ?? null)) {
            return (string) $diagnostics[0]['stopReason'];
        }

        return 'SEARCH_PLAN_COMPLETED';
    }

    /** @param list<array<string, mixed>> $diagnostics */
    private function firstSearchStatusCode(array $diagnostics): int
    {
        foreach ($diagnostics as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }
            $statuses = is_array($diagnostic['statusCodes'] ?? null) ? $diagnostic['statusCodes'] : [];
            if ($statuses !== []) {
                return max(0, (int) $statuses[0]);
            }
        }

        return 0;
    }

    /** @param list<array<string, mixed>> $diagnostics */
    private function firstSearchRequestedUrl(array $diagnostics): ?string
    {
        foreach ($diagnostics as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }
            $url = trim((string) ($diagnostic['requestedUrl'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        return null;
    }

    /** @param list<array<string, mixed>> $diagnostics */
    private function lastSearchUrl(array $diagnostics): ?string
    {
        $last = null;
        foreach ($diagnostics as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }
            $history = is_array($diagnostic['history'] ?? null) ? $diagnostic['history'] : [];
            foreach ($history as $page) {
                if (!is_array($page)) {
                    continue;
                }
                $url = trim((string) ($page['url'] ?? ''));
                if ($url !== '') {
                    $last = $url;
                }
            }
            if ($history === []) {
                $requestedUrl = trim((string) ($diagnostic['requestedUrl'] ?? ''));
                if ($requestedUrl !== '') {
                    $last = $requestedUrl;
                }
            }
        }

        return $last;
    }

    /** @param list<array<string, mixed>> $diagnostics @return list<array<string, mixed>> */
    private function multiSearchPaginationHistory(array $diagnostics): array
    {
        $history = [];
        foreach ($diagnostics as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }
            $keyword = is_string($diagnostic['keyword'] ?? null) ? $diagnostic['keyword'] : null;
            foreach (is_array($diagnostic['history'] ?? null) ? $diagnostic['history'] : [] as $page) {
                if (!is_array($page)) {
                    continue;
                }
                $history[] = [
                    'keyword' => $keyword,
                    ...$page,
                ];
            }
        }

        return $history;
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
