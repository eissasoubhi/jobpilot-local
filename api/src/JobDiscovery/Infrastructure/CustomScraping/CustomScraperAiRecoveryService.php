<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\CustomScraping;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Application\CustomScraperAiFallbackCoordinator;
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

final class CustomScraperAiRecoveryService
{
    private const MAX_AI_PAGES = 2;
    private const MAX_DETAILS = 10;

    public function __construct(
        private ControlledHttpScrapingClient $httpClient,
        private GenericHtmlModeDetector $modeDetector,
        private GenericJobListingExtractor $listingExtractor,
        private GenericPaginationDetector $paginationDetector,
        private CustomScraperAiFallbackCoordinator $aiFallback,
        private CustomScraperDetailPriority $detailPriority,
        private GenericJobDetailExtractor $detailExtractor,
        private CustomScraperOfferQualityEvaluator $qualityEvaluator,
    ) {
    }

    /**
     * @param list<string> $targetJobs
     * @param list<string> $skills
     * @return array{offers: list<array<string, mixed>>, diagnostics: array<string, mixed>}
     */
    public function recover(CustomScraperSource $source, array $targetJobs, array $skills): array
    {
        $data = $source->toArray();
        if (($data['authorizationConfirmed'] ?? false) !== true) {
            return $this->empty('AUTHORIZATION_REVOKED');
        }
        if (($data['mode'] ?? CustomScraperSource::MODE_AUTO) === CustomScraperSource::MODE_BROWSER) {
            return $this->empty('BROWSER_SOURCE');
        }

        $id = $data['id'] ?? null;
        if (!is_int($id)) {
            return $this->empty('SOURCE_NOT_PERSISTED');
        }

        $domain = strtolower((string) ($data['domain'] ?? ''));
        $listingUrl = (string) ($data['listingUrl'] ?? '');
        $sourceName = (string) ($data['name'] ?? $domain);
        $pageLimit = min(self::MAX_AI_PAGES, max(1, (int) ($data['maxPages'] ?? 1)));
        $detailLimit = min(self::MAX_DETAILS, max(0, (int) ($data['maxDetails'] ?? 0)));
        $checkedAt = is_string($data['authorizationCheckedAt'] ?? null)
            ? new \DateTimeImmutable((string) $data['authorizationCheckedAt'])
            : new \DateTimeImmutable('today');
        $policy = new ConnectorPolicy(
            ConnectorComplianceStatus::ALLOWED,
            $checkedAt,
            is_string($data['authorizationReference'] ?? null)
                ? $data['authorizationReference']
                : 'Autorisation confirmée par l’utilisateur.',
            maxRequestsPerSync: $pageLimit + $detailLimit,
            dailyQuota: 300,
            minimumDelayMilliseconds: 1_000,
            respectsRobotsTxt: true,
        );
        $connectorCode = 'custom-scraper-'.$id;

        $pageUrl = $listingUrl;
        $visited = [];
        $aiCandidates = [];
        $fallbackAttempts = 0;
        $pagesInspected = 0;
        $networkRequests = 0;
        $stopReason = 'NO_NEXT_PAGE';
        $pageError = null;

        while ($pagesInspected < $pageLimit) {
            $normalized = $this->normalizeUrl($pageUrl);
            if (isset($visited[$normalized])) {
                $stopReason = 'LOOP_DETECTED';
                break;
            }
            $visited[$normalized] = true;

            try {
                $response = $this->httpClient->fetch(new HttpScrapingRequest(
                    $connectorCode,
                    $pageUrl,
                    $policy,
                    timeoutSeconds: 10,
                    maxRetries: 0,
                    initialBackoffMilliseconds: 0,
                    maxResponseBytes: 3_000_000,
                ));
            } catch (\RuntimeException $exception) {
                $pageError = $exception->getMessage();
                $stopReason = 'PAGE_FETCH_ERROR';
                break;
            }

            ++$pagesInspected;
            $networkRequests += $response->attempts;
            $analysis = $this->modeDetector->analyze($response->body);
            if (($analysis['recommendedMode'] ?? CustomScraperSource::MODE_HTTP) === CustomScraperSource::MODE_BROWSER) {
                $stopReason = 'BROWSER_REQUIRED';
                break;
            }

            $deterministic = $this->listingExtractor->extract($response->body, $response->url, $sourceName);
            $fallback = $this->aiFallback->extractIfNeeded(
                $response->body,
                $response->url,
                $sourceName,
                $analysis,
                $deterministic,
                $fallbackAttempts,
                self::MAX_AI_PAGES,
            );
            if ($fallback['attempted']) {
                ++$fallbackAttempts;
                foreach ($fallback['candidates'] as $candidate) {
                    if (!is_array($candidate)) {
                        continue;
                    }
                    $url = trim((string) ($candidate['sourceUrl'] ?? ''));
                    if ($url === '' || !$this->sameDomainHttps($url, $domain)) {
                        continue;
                    }
                    $aiCandidates[$url] = $candidate;
                }
            }

            $pagination = $this->paginationDetector->detect($response->body, $response->url);
            $nextUrl = is_string($pagination['nextUrl'] ?? null) ? $pagination['nextUrl'] : null;
            if ($nextUrl === null) {
                $stopReason = 'NO_NEXT_PAGE';
                break;
            }
            if (isset($visited[$this->normalizeUrl($nextUrl)])) {
                $stopReason = 'LOOP_DETECTED';
                break;
            }
            if ($pagesInspected >= $pageLimit) {
                $stopReason = 'AI_PAGE_LIMIT_REACHED';
                break;
            }
            $pageUrl = $nextUrl;
        }

        $candidates = array_values($aiCandidates);
        $detailEnriched = 0;
        $detailError = null;
        foreach ($this->detailPriority->rank($candidates, $targetJobs, $skills) as $index) {
            if ($detailEnriched >= $detailLimit) {
                break;
            }
            $candidate = $candidates[$index] ?? null;
            if (!is_array($candidate)) {
                continue;
            }
            $detailUrl = trim((string) ($candidate['sourceUrl'] ?? ''));
            if (!$this->sameDomainHttps($detailUrl, $domain)) {
                continue;
            }

            try {
                $detailResponse = $this->httpClient->fetch(new HttpScrapingRequest(
                    $connectorCode,
                    $detailUrl,
                    $policy,
                    timeoutSeconds: 10,
                    maxRetries: 0,
                    initialBackoffMilliseconds: 0,
                    maxResponseBytes: 3_000_000,
                ));
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

        $reliable = [];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $quality = $this->qualityEvaluator->evaluate($candidate, $domain);
            $rawData = is_array($candidate['rawData'] ?? null) ? $candidate['rawData'] : [];
            $candidate['rawData'] = [...$rawData, 'quality' => $quality];
            if ($quality['reliable']) {
                $reliable[] = $candidate;
            }
        }

        return [
            'offers' => $reliable,
            'diagnostics' => [
                'attempted' => $fallbackAttempts > 0,
                'fallbackAttempts' => $fallbackAttempts,
                'attemptLimit' => self::MAX_AI_PAGES,
                'pagesInspected' => $pagesInspected,
                'pageLimit' => $pageLimit,
                'aiCandidateCount' => count($candidates),
                'detailEnriched' => $detailEnriched,
                'detailLimit' => $detailLimit,
                'reliableCount' => count($reliable),
                'networkRequests' => $networkRequests,
                'stopReason' => $stopReason,
                'pageError' => $pageError,
                'detailError' => $detailError,
            ],
        ];
    }

    /** @return array{offers: list<array<string, mixed>>, diagnostics: array<string, mixed>} */
    private function empty(string $reason): array
    {
        return [
            'offers' => [],
            'diagnostics' => [
                'attempted' => false,
                'fallbackAttempts' => 0,
                'attemptLimit' => self::MAX_AI_PAGES,
                'pagesInspected' => 0,
                'aiCandidateCount' => 0,
                'detailEnriched' => 0,
                'reliableCount' => 0,
                'networkRequests' => 0,
                'stopReason' => $reason,
            ],
        ];
    }

    private function sameDomainHttps(string $url, string $domain): bool
    {
        $parts = parse_url(trim($url));

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === $domain
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    private function normalizeUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$path.$query;
    }
}
