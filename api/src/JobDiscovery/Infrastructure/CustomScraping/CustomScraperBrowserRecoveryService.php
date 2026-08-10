<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\CustomScraping;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Application\BrowserRenderClientInterface;
use App\JobDiscovery\Application\CustomScraperBrowserRenderCoordinator;
use App\JobDiscovery\Application\CustomScraperDetailPriority;
use App\JobDiscovery\Application\CustomScraperOfferQualityEvaluator;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobDetailExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobListingExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericPaginationDetector;
use App\Service\CustomScraperDiagnosticService;

final class CustomScraperBrowserRecoveryService
{
    private const MAX_PAGES = 3;
    private const MAX_DETAILS = 10;

    public function __construct(
        private CustomScraperDiagnosticService $diagnostic,
        private CustomScraperBrowserRenderCoordinator $browser,
        private BrowserRenderClientInterface $browserClient,
        private GenericJobListingExtractor $listingExtractor,
        private GenericPaginationDetector $paginationDetector,
        private CustomScraperDetailPriority $detailPriority,
        private GenericJobDetailExtractor $detailExtractor,
        private CustomScraperOfferQualityEvaluator $qualityEvaluator,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->browserClient->isConfigured();
    }

    /**
     * @param list<string> $targetJobs
     * @param list<string> $skills
     * @return array{offers: list<array<string, mixed>>, diagnostics: array<string, mixed>}
     */
    public function recover(CustomScraperSource $source, array $targetJobs, array $skills): array
    {
        $data = $source->toArray();
        if (($data['enabled'] ?? false) !== true) {
            return $this->empty('SOURCE_DISABLED');
        }
        if (($data['authorizationConfirmed'] ?? false) !== true) {
            return $this->empty('AUTHORIZATION_REVOKED');
        }
        if (!$this->isConfigured()) {
            return $this->empty('BROWSER_WORKER_NOT_CONFIGURED');
        }

        $domain = strtolower((string) ($data['domain'] ?? ''));
        $sourceName = (string) ($data['name'] ?? $domain);
        $configuredMode = (string) ($data['mode'] ?? CustomScraperSource::MODE_AUTO);
        $pageLimit = min(self::MAX_PAGES, max(1, (int) ($data['maxPages'] ?? 1)));
        $detailLimit = min(self::MAX_DETAILS, max(0, (int) ($data['maxDetails'] ?? 0)));
        $sourceCode = is_int($data['id'] ?? null) ? 'custom-scraper-'.$data['id'] : 'custom-browser-preview';

        $pageUrl = (string) ($data['listingUrl'] ?? '');
        $visited = [];
        $candidatesByUrl = [];
        $pagesRendered = 0;
        $detailRendered = 0;
        $allowedRequests = 0;
        $blockedRequests = 0;
        $stopReason = 'NO_NEXT_PAGE';
        $browserError = null;

        while ($pagesRendered < $pageLimit) {
            $normalized = $this->normalizeUrl($pageUrl);
            if (isset($visited[$normalized])) {
                $stopReason = 'LOOP_DETECTED';
                break;
            }
            $visited[$normalized] = true;

            $probeSource = $this->probeSource($source, $pageUrl);
            try {
                $probe = $this->diagnostic->diagnose($probeSource);
            } catch (\Throwable $exception) {
                $browserError = $exception->getMessage();
                $stopReason = 'ROBOTS_OR_HTTP_PREFLIGHT_FAILED';
                break;
            }

            $recommendedMode = (string) ($probe['recommendedMode'] ?? CustomScraperSource::MODE_HTTP);
            try {
                $render = $this->browser->renderIfAllowed(
                    $sourceCode,
                    $pageUrl,
                    $domain,
                    $configuredMode,
                    $recommendedMode,
                    true,
                    true,
                );
            } catch (\Throwable $exception) {
                $browserError = $exception->getMessage();
                $stopReason = 'BROWSER_RENDER_FAILED';
                break;
            }

            if (($render['rendered'] ?? false) !== true || !is_array($render['result'] ?? null)) {
                $stopReason = 'BROWSER_POLICY_REFUSED';
                break;
            }

            $result = $render['result'];
            $html = (string) ($result['html'] ?? '');
            $finalUrl = (string) ($result['finalUrl'] ?? $pageUrl);
            ++$pagesRendered;
            $allowedRequests += max(0, (int) ($result['allowedRequests'] ?? 0));
            $blockedRequests += max(0, (int) ($result['blockedRequests'] ?? 0));

            foreach ($this->listingExtractor->extract($html, $finalUrl, $sourceName) as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }
                $url = trim((string) ($candidate['sourceUrl'] ?? ''));
                if ($url === '' || !$this->sameDomainHttps($url, $domain)) {
                    continue;
                }
                $existing = $candidatesByUrl[$url] ?? null;
                if (!is_array($existing) || $this->richness($candidate) > $this->richness($existing)) {
                    $candidatesByUrl[$url] = $candidate;
                }
            }

            $pagination = $this->paginationDetector->detect($html, $finalUrl);
            $nextUrl = is_string($pagination['nextUrl'] ?? null) ? $pagination['nextUrl'] : null;
            if ($nextUrl === null) {
                $stopReason = 'NO_NEXT_PAGE';
                break;
            }
            if (isset($visited[$this->normalizeUrl($nextUrl)])) {
                $stopReason = 'LOOP_DETECTED';
                break;
            }
            if ($pagesRendered >= $pageLimit) {
                $stopReason = 'PAGE_LIMIT_REACHED';
                break;
            }
            $pageUrl = $nextUrl;
        }

        $candidates = array_values($candidatesByUrl);
        $detailError = null;
        foreach ($this->detailPriority->rank($candidates, $targetJobs, $skills) as $index) {
            if ($detailRendered >= $detailLimit) {
                break;
            }
            $candidate = $candidates[$index] ?? null;
            if (!is_array($candidate) || !$this->needsDetail($candidate)) {
                continue;
            }
            $detailUrl = trim((string) ($candidate['sourceUrl'] ?? ''));
            if (!$this->sameDomainHttps($detailUrl, $domain)) {
                continue;
            }

            $detailProbeSource = $this->probeSource($source, $detailUrl);
            try {
                $detailProbe = $this->diagnostic->diagnose($detailProbeSource);
                $detailRender = $this->browser->renderIfAllowed(
                    $sourceCode,
                    $detailUrl,
                    $domain,
                    $configuredMode,
                    (string) ($detailProbe['recommendedMode'] ?? CustomScraperSource::MODE_HTTP),
                    true,
                    true,
                );
            } catch (\Throwable $exception) {
                $detailError = $exception->getMessage();
                break;
            }

            if (($detailRender['rendered'] ?? false) !== true || !is_array($detailRender['result'] ?? null)) {
                continue;
            }
            $result = $detailRender['result'];
            $candidates[$index] = $this->detailExtractor->enrich(
                (string) ($result['html'] ?? ''),
                $candidate,
                (string) ($result['finalUrl'] ?? $detailUrl),
                $sourceName,
            );
            ++$detailRendered;
            $allowedRequests += max(0, (int) ($result['allowedRequests'] ?? 0));
            $blockedRequests += max(0, (int) ($result['blockedRequests'] ?? 0));
        }

        $reliable = [];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $quality = $this->qualityEvaluator->evaluate($candidate, $domain);
            $rawData = is_array($candidate['rawData'] ?? null) ? $candidate['rawData'] : [];
            $candidate['rawData'] = [
                ...$rawData,
                'browserRendered' => true,
                'quality' => $quality,
            ];
            if ($quality['reliable']) {
                $reliable[] = $candidate;
            }
        }

        return [
            'offers' => $reliable,
            'diagnostics' => [
                'attempted' => $pagesRendered > 0,
                'pagesRendered' => $pagesRendered,
                'pageLimit' => $pageLimit,
                'detailRendered' => $detailRendered,
                'detailLimit' => $detailLimit,
                'candidateCount' => count($candidates),
                'reliableCount' => count($reliable),
                'allowedBrowserRequests' => $allowedRequests,
                'blockedBrowserRequests' => $blockedRequests,
                'stopReason' => $stopReason,
                'browserError' => $browserError,
                'detailError' => $detailError,
            ],
        ];
    }

    private function probeSource(CustomScraperSource $source, string $url): CustomScraperSource
    {
        $data = $source->toArray();
        $probe = new CustomScraperSource((string) ($data['name'] ?? 'Custom source'), $url);
        $payload = [
            'mode' => (string) ($data['mode'] ?? CustomScraperSource::MODE_AUTO),
            'authorizationConfirmed' => true,
            'authorizationReference' => is_string($data['authorizationReference'] ?? null)
                ? $data['authorizationReference']
                : 'Autorisation confirmée par l’utilisateur.',
        ];
        if (is_string($data['authorizationCheckedAt'] ?? null)) {
            $payload['authorizationCheckedAt'] = $data['authorizationCheckedAt'];
        }
        $probe->fill($payload);
        return $probe;
    }

    /** @param array<string, mixed> $candidate */
    private function needsDetail(array $candidate): bool
    {
        $rawData = is_array($candidate['rawData'] ?? null) ? $candidate['rawData'] : [];
        return ($rawData['needsDetailFetch'] ?? false) === true
            || mb_strlen(trim((string) ($candidate['description'] ?? ''))) < 60;
    }

    private function sameDomainHttps(string $url, string $domain): bool
    {
        $parts = parse_url(trim($url));
        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === $domain
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    /** @param array<string, mixed> $candidate */
    private function richness(array $candidate): int
    {
        $score = mb_strlen(trim((string) ($candidate['description'] ?? '')));
        foreach (['company', 'location', 'contractType', 'workMode', 'publishedAt'] as $field) {
            if (trim((string) ($candidate[$field] ?? '')) !== '') {
                $score += 100;
            }
        }
        return $score;
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

    /** @return array{offers: list<array<string, mixed>>, diagnostics: array<string, mixed>} */
    private function empty(string $reason): array
    {
        return [
            'offers' => [],
            'diagnostics' => [
                'attempted' => false,
                'pagesRendered' => 0,
                'detailRendered' => 0,
                'candidateCount' => 0,
                'reliableCount' => 0,
                'allowedBrowserRequests' => 0,
                'blockedBrowserRequests' => 0,
                'stopReason' => $reason,
            ],
        ];
    }
}
