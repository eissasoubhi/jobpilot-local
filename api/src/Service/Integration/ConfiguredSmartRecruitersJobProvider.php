<?php

declare(strict_types=1);

namespace App\Service\Integration;

use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use App\JobDiscovery\Domain\Connector\VersionedJobSourceConnector;
use App\Service\SmartRecruitersJobProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ConfiguredSmartRecruitersJobProvider implements GovernedJobSourceConnector, VersionedJobSourceConnector
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ExternalIntegrationConfigurationStore $configuration,
        private int $pages = 1,
        private int $resultsPerPage = 100,
        private int $maxDetails = 20,
    ) {
    }

    public function code(): string
    {
        return 'smartrecruiters';
    }

    public function name(): string
    {
        return 'SmartRecruiters';
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
        $credentials = $this->configuration->effective('smartrecruiters');
        $hasToken = trim($credentials['apiToken'] ?? '') !== '';
        $hasCompanies = trim($credentials['companyIdentifiers'] ?? '') !== '';

        if ($hasToken && $hasCompanies) {
            return null;
        }
        if (!$hasToken && !$hasCompanies) {
            return 'Renseigne SMARTRECRUITERS_API_TOKEN et SMARTRECRUITERS_COMPANY_IDENTIFIERS dans Configuration & clés API ou dans le fichier .env.';
        }
        if (!$hasToken) {
            return 'Renseigne SMARTRECRUITERS_API_TOKEN dans Configuration & clés API ou dans le fichier .env.';
        }

        return 'Renseigne au moins un identifiant valide dans SMARTRECRUITERS_COMPANY_IDENTIFIERS via Configuration & clés API ou le fichier .env.';
    }

    public function search(array $targetJobs, array $skills): array
    {
        return $this->delegate()->search($targetJobs, $skills);
    }

    private function delegate(): SmartRecruitersJobProvider
    {
        $credentials = $this->configuration->effective('smartrecruiters');

        return new SmartRecruitersJobProvider(
            $this->httpClient,
            $credentials['apiToken'] ?? '',
            $credentials['companyIdentifiers'] ?? '',
            $this->pages,
            $this->resultsPerPage,
            $this->maxDetails,
        );
    }
}
