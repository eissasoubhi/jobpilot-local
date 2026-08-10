<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericHtmlModeDetector;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericJobListingExtractor;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingRequest;

final class CustomScraperExtractionService
{
    public function __construct(
        private ControlledHttpScrapingClient $httpClient,
        private GenericHtmlModeDetector $modeDetector,
        private GenericJobListingExtractor $extractor,
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
        $checkedAt = is_string($data['authorizationCheckedAt'] ?? null)
            ? new \DateTimeImmutable((string) $data['authorizationCheckedAt'])
            : new \DateTimeImmutable('today');

        $policy = new ConnectorPolicy(
            ConnectorComplianceStatus::ALLOWED,
            $checkedAt,
            is_string($data['authorizationReference'] ?? null) ? $data['authorizationReference'] : 'Autorisation confirmée par l’utilisateur.',
            maxRequestsPerSync: 1,
            dailyQuota: 20,
            minimumDelayMilliseconds: 1_000,
            respectsRobotsTxt: true,
        );

        $response = $this->httpClient->fetch(new HttpScrapingRequest(
            'custom-preview-'.substr(hash('sha256', $domain.'|'.$listingUrl), 0, 16),
            $listingUrl,
            $policy,
            timeoutSeconds: 10,
            maxRetries: 0,
            initialBackoffMilliseconds: 0,
            maxResponseBytes: 3_000_000,
        ));

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

        return [
            'configuredMode' => $configuredMode,
            'recommendedMode' => $recommendedMode,
            'effectiveMode' => $effectiveMode,
            'requiresBrowser' => $requiresBrowser,
            'candidateCount' => count($candidates),
            'candidates' => $candidates,
            'signals' => $analysis['signals'] ?? [],
            'http' => [
                'requestedUrl' => $listingUrl,
                'finalUrl' => $response->url,
                'statusCode' => $response->statusCode,
                'responseBytes' => strlen($response->body),
                'networkRequests' => $response->attempts,
                'fromCache' => $response->fromCache,
            ],
        ];
    }
}
