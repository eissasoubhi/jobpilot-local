<?php

declare(strict_types=1);

namespace App\Service\Integration;

use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use App\Service\AdzunaJobProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ConfiguredAdzunaJobProvider implements GovernedJobSourceConnector
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ExternalIntegrationConfigurationStore $configuration,
        private string $country = 'fr',
        private string $where = '',
        private int $resultsPerQuery = 20,
    ) {
    }

    public function code(): string
    {
        return 'adzuna';
    }

    public function name(): string
    {
        return 'Adzuna';
    }

    public function mode(): ConnectorMode
    {
        return ConnectorMode::API;
    }

    public function policy(): ConnectorPolicy
    {
        return $this->delegate()->policy();
    }

    public function isConfigured(): bool
    {
        return $this->delegate()->isConfigured();
    }

    public function configurationMessage(): ?string
    {
        return $this->isConfigured()
            ? null
            : 'Renseigne ADZUNA_APP_ID et ADZUNA_APP_KEY dans Configuration & clés API ou dans le fichier .env.';
    }

    public function search(array $targetJobs, array $skills): array
    {
        return $this->delegate()->search($targetJobs, $skills);
    }

    private function delegate(): AdzunaJobProvider
    {
        $credentials = $this->configuration->effective('adzuna');

        return new AdzunaJobProvider(
            $this->httpClient,
            $credentials['appId'] ?? '',
            $credentials['appKey'] ?? '',
            $this->country,
            $this->where,
            $this->resultsPerQuery,
        );
    }
}
