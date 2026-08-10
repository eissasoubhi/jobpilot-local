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

        return $this->run(
            $data,
            pageLimit: min(self::HARD_MAX_SYNC_PAGES, max(1, (int) ($data['maxPages'] ?? 1))),
            detailLimit: min(self::HARD_MAX_SYNC_DETAILS, max(0, (int) ($data['maxDetails'] ?? 0))),
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
