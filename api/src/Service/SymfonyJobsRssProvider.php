<?php

declare(strict_types=1);

namespace App\Service;

use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use App\JobDiscovery\Domain\Connector\VersionedJobSourceConnector;
use App\JobDiscovery\Infrastructure\Feed\SyndicationJobFeedParser;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingRequest;

final class SymfonyJobsRssProvider implements JobProviderInterface, GovernedJobSourceConnector, VersionedJobSourceConnector
{
    private const ENDPOINT = 'https://symfony.com/jobs.rss';

    public function __construct(
        private ControlledHttpScrapingClient $httpClient,
        private SyndicationJobFeedParser $parser,
        private bool $enabled = true,
    ) {
    }

    public function code(): string
    {
        return 'symfony-jobs';
    }

    public function name(): string
    {
        return 'Symfony Jobs';
    }

    public function mode(): ConnectorMode
    {
        return ConnectorMode::RSS;
    }

    public function parserVersion(): string
    {
        return 'syndication-v1';
    }

    public function isConfigured(): bool
    {
        return $this->enabled;
    }

    public function configurationMessage(): ?string
    {
        return $this->enabled
            ? 'Flux RSS officiel publié sur la page Symfony Jobs.'
            : 'Le connecteur est désactivé par SYMFONY_JOBS_RSS_ENABLED.';
    }

    public function policy(): ConnectorPolicy
    {
        return new ConnectorPolicy(
            ConnectorComplianceStatus::ALLOWED,
            new \DateTimeImmutable('2026-08-05'),
            'Symfony expose explicitement un lien « Jobs RSS » sur son job board officiel. Le connecteur lit uniquement ce flux de syndication, sans parcourir les pages HTML.',
            maxRequestsPerSync: 4,
            dailyQuota: 16,
            minimumDelayMilliseconds: 1_000,
            respectsRobotsTxt: false,
        );
    }

    public function search(array $targetJobs, array $skills): array
    {
        if (!$this->enabled) {
            return [];
        }

        $response = $this->httpClient->fetch(new HttpScrapingRequest(
            $this->code(),
            self::ENDPOINT,
            $this->policy(),
            timeoutSeconds: 10,
            maxRetries: 2,
            initialBackoffMilliseconds: 250,
            maxResponseBytes: 2_000_000,
        ));

        return $this->parser->parse($response->body, $this->name());
    }
}
