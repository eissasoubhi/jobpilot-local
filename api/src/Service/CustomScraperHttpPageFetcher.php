<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingRequest;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingResult;

final class CustomScraperHttpPageFetcher
{
    public function __construct(private ControlledHttpScrapingClient $httpClient)
    {
    }

    public function fetchListing(CustomScraperSource $source): HttpScrapingResult
    {
        $data = $source->toArray();
        if (($data['authorizationConfirmed'] ?? false) !== true) {
            throw new \InvalidArgumentException('L’autorisation de collecte doit être confirmée avant de contacter ce site.');
        }

        $listingUrl = trim((string) ($data['listingUrl'] ?? ''));
        $domain = trim((string) ($data['domain'] ?? ''));
        if ($listingUrl === '' || $domain === '') {
            throw new \InvalidArgumentException('La source de scraping ne contient pas une URL publique exploitable.');
        }

        $checkedAt = is_string($data['authorizationCheckedAt'] ?? null)
            ? new \DateTimeImmutable((string) $data['authorizationCheckedAt'])
            : new \DateTimeImmutable('today');

        $policy = new ConnectorPolicy(
            ConnectorComplianceStatus::ALLOWED,
            $checkedAt,
            is_string($data['authorizationReference'] ?? null)
                ? $data['authorizationReference']
                : 'Autorisation confirmée par l’utilisateur.',
            maxRequestsPerSync: 1,
            dailyQuota: 10,
            minimumDelayMilliseconds: 1_000,
            respectsRobotsTxt: true,
        );

        return $this->httpClient->fetch(new HttpScrapingRequest(
            'custom-'.substr(hash('sha256', $domain.'|'.$listingUrl), 0, 16),
            $listingUrl,
            $policy,
            timeoutSeconds: 10,
            maxRetries: 0,
            initialBackoffMilliseconds: 0,
            maxResponseBytes: 3_000_000,
        ));
    }
}
