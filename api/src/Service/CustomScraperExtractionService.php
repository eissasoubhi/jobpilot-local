<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Application\CustomScraperOfferQualityEvaluator;
use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericHtmlModeDetector;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobDetailExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobListingExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingRequest;

final class CustomScraperExtractionService
{
    private const HARD_MAX_DETAIL_PREVIEW = 10;

    public function __construct(
        private ControlledHttpScrapingClient $httpClient,
        private GenericHtmlModeDetector $modeDetector,
        private GenericJobListingExtractor $extractor,
        private GenericJobDetailExtractor $detailExtractor,
        private CustomScraperOfferQualityEvaluator $qualityEvaluator,
    ) {
    }

    /** @return array<string, mixed> */
    public function preview(CustomScraperSource $source): array
    {
        $data = $source->toArray();
        if (($data['authorizationConfirmed'] ?? false) !== true) {
            throw new \InvalidArgumentException('L’autorisation de collecte doit être confirmée avant d’extraire des offres.');
        }

        $configuredMode = (string) ($data['mode'] ?? CustomScraperSource::MODE_AUTO);
        if ($configuredMode === CustomScraperSource::MODE_BROWSER) {
            throw new \RuntimeException('Cette source force Browser/Playwright. Le worker navigateur n’est pas encore activé pour l’extraction.');
        }

        $listingUrl = (string) ($data['listingUrl'] ?? '');
        $domain = (string) ($data['domain'] ?? '');
        $sourceName = (string) ($data['name'] ?? $domain);
        $detailLimit = min(
            self::HARD_MAX_DETAIL_PREVIEW,
            max(0, (int) ($data['maxDetails'] ?? 0)),
        );
        $checkedAt = is_string($data['authorizationCheckedAt'] ?? null)
            ? new \DateTimeImmutable((string) $data['authorizationCheckedAt'])
            : new \DateTimeImmutable('today');
        $connectorCode = 'custom-preview-'.substr(hash('sha256', $domain.'|'.$listingUrl), 0, 16);

        $policy = new ConnectorPolicy(
            ConnectorComplianceStatus::ALLOWED,
            $checkedAt,
            is_string($data['authorizationReference'] ?? null) ? $data['authorizationReference'] : 'Autorisation confirmée par l’utilisateur.',
            maxRequestsPerSync: 1 + $detailLimit,
            dailyQuota: 100,
            minimumDelayMilliseconds: 1_000,
            respectsRobotsTxt: true,
        );

        $response = $this->httpClient->fetch(new HttpScrapingRequest(
            $connectorCode,
            $listingUrl,
            $policy,
            timeoutSeconds: 10,
            maxRetries: 0,
            initialBackoffMilliseconds: 0,
            maxResponseBytes: 3_000_000,
        ));

        $networkRequests = $response->attempts;
        $analysis = $this->modeDetector->analyze($response->body);
        $recommendedMode = (string) ($analysis['recommendedMode'] ?? CustomScraperSource::MODE_HTTP);
        $effectiveMode = $configuredMode === CustomScraperSource::MODE_AUTO
            ? $recommendedMode
            : $configuredMode;
        $candidates = $effectiveMode === CustomScraperSource::MODE_HTTP
            ? $this->extractor->extract($response->body, $response->url, $sourceName)
            : [];
        $requiresBrowser = $effectiveMode === CustomScraperSource::MODE_BROWSER
            || ($recommendedMode === CustomScraperSource::MODE_BROWSER && $candidates === []);

        $detailEnriched = 0;
        $detailError = null;
        if ($effectiveMode === CustomScraperSource::MODE_HTTP && $detailLimit > 0 && $candidates !== []) {
            foreach ($candidates as $index => $candidate) {
                if ($detailEnriched >= $detailLimit) {
                    break;
                }
                if (!$this->needsDetailFetch($candidate)) {
                    continue;
                }

                $detailUrl = $this->eligibleDetailUrl((string) ($candidate['sourceUrl'] ?? ''), $domain, $response->url);
                if ($detailUrl === null) {
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
            'candidates' => array_values($candidates),
            'signals' => $analysis['signals'] ?? [],
            'http' => [
                'requestedUrl' => $listingUrl,
                'finalUrl' => $response->url,
                'statusCode' => $response->statusCode,
                'responseBytes' => strlen($response->body),
                'networkRequests' => $networkRequests,
                'fromCache' => $response->fromCache,
            ],
        ];
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

    private function eligibleDetailUrl(string $url, string $domain, string $listingUrl): ?string
    {
        $url = trim($url);
        if ($url === '' || rtrim($url, '/') === rtrim($listingUrl, '/')) {
            return null;
        }

        $parts = parse_url($url);
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== strtolower($domain)) {
            return null;
        }

        return $url;
    }
}
