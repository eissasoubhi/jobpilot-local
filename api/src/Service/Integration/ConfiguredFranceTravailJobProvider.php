<?php

declare(strict_types=1);

namespace App\Service\Integration;

use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use App\JobDiscovery\Domain\Connector\SearchDiagnosticsConnector;
use App\JobDiscovery\Domain\Connector\VersionedJobSourceConnector;
use App\Service\FranceTravailJobProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ConfiguredFranceTravailJobProvider implements GovernedJobSourceConnector, SearchDiagnosticsConnector, VersionedJobSourceConnector
{
    /** @var array<string, mixed> */
    private array $lastSearchDiagnostics = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ExternalIntegrationConfigurationStore $configuration,
        private readonly string $scope = 'api_offresdemploiv2 o2dsoffre',
        private readonly string $tokenEndpoint = 'https://entreprise.francetravail.fr/connexion/oauth2/access_token?realm=/partenaire',
        private readonly string $searchEndpoint = 'https://api.francetravail.io/partenaire/offresdemploi/v2/offres/search',
        private readonly int $resultsPerQuery = 50,
    ) {
    }

    public function code(): string
    {
        return 'france-travail';
    }

    public function name(): string
    {
        return 'France Travail';
    }

    public function mode(): ConnectorMode
    {
        return ConnectorMode::API;
    }

    public function parserVersion(): string
    {
        return $this->delegate()->parserVersion();
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
            : 'Renseigne le client ID et le client secret France Travail dans Configuration & clés API ou dans le fichier .env.';
    }

    public function search(array $targetJobs, array $skills): array
    {
        $delegate = $this->delegate();

        try {
            return $delegate->search($targetJobs, $skills);
        } finally {
            $this->lastSearchDiagnostics = $delegate->searchDiagnostics();
        }
    }

    public function searchDiagnostics(): array
    {
        return $this->lastSearchDiagnostics;
    }

    private function delegate(): FranceTravailJobProvider
    {
        $credentials = $this->configuration->effective('france-travail');

        return new FranceTravailJobProvider(
            $this->httpClient,
            $credentials['clientId'] ?? '',
            $credentials['clientSecret'] ?? '',
            $this->scope,
            $this->tokenEndpoint,
            $this->searchEndpoint,
            $this->resultsPerQuery,
        );
    }
}
