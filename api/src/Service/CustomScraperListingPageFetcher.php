<?php

declare(strict_types=1);

namespace App\Service;

use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingRequest;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingResult;

class CustomScraperListingPageFetcher
{
    public function __construct(private ControlledHttpScrapingClient $httpClient)
    {
    }

    public function fetch(string $connectorCode, string $url, ConnectorPolicy $policy): HttpScrapingResult
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
}
