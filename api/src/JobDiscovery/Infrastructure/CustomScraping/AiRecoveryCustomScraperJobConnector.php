<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\CustomScraping;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use App\JobDiscovery\Domain\Connector\ScheduledJobSourceConnector;
use App\JobDiscovery\Domain\Connector\SearchDiagnosticsConnector;
use App\JobDiscovery\Domain\Connector\VersionedJobSourceConnector;
use App\Service\CustomScraperExtractionService;

final class AiRecoveryCustomScraperJobConnector implements GovernedJobSourceConnector, VersionedJobSourceConnector, SearchDiagnosticsConnector, ScheduledJobSourceConnector
{
    private CustomScraperJobConnector $inner;

    /** @var array<string, mixed> */
    private array $diagnostics = [];

    public function __construct(
        private CustomScraperSource $source,
        CustomScraperExtractionService $extraction,
        private CustomScraperAiRecoveryService $recovery,
    ) {
        $this->inner = new CustomScraperJobConnector($source, $extraction);
    }

    public function code(): string
    {
        return $this->inner->code();
    }

    public function name(): string
    {
        return $this->inner->name();
    }

    public function mode(): ConnectorMode
    {
        return $this->inner->mode();
    }

    public function parserVersion(): string
    {
        return 'custom-generic-html-v3-ai-recovery';
    }

    public function syncIntervalSeconds(): int
    {
        return $this->inner->syncIntervalSeconds();
    }

    public function isConfigured(): bool
    {
        return $this->inner->isConfigured();
    }

    public function configurationMessage(): ?string
    {
        $message = $this->inner->configurationMessage();
        if (!$this->inner->isConfigured()) {
            return $message;
        }

        return trim((string) $message).' Fallback Gemini grounded uniquement si l’extraction déterministe ne produit aucune offre fiable.';
    }

    public function policy(): ConnectorPolicy
    {
        return $this->inner->policy();
    }

    public function search(array $targetJobs, array $skills): array
    {
        $offers = $this->inner->search($targetJobs, $skills);
        $baseDiagnostics = $this->inner->searchDiagnostics();

        if ($offers !== [] || !$this->inner->isConfigured()) {
            $this->diagnostics = [
                ...$baseDiagnostics,
                'aiRecovery' => [
                    'attempted' => false,
                    'stopReason' => $offers !== [] ? 'RELIABLE_DETERMINISTIC_OFFERS' : 'CONNECTOR_NOT_CONFIGURED',
                ],
            ];

            return $offers;
        }

        $recovery = $this->recovery->recover($this->source, $targetJobs, $skills);
        $recoveredOffers = is_array($recovery['offers'] ?? null) ? $recovery['offers'] : [];
        $recoveryDiagnostics = is_array($recovery['diagnostics'] ?? null) ? $recovery['diagnostics'] : [];
        $this->diagnostics = [
            ...$baseDiagnostics,
            'aiRecovery' => $recoveryDiagnostics,
        ];

        return array_values(array_filter($recoveredOffers, 'is_array'));
    }

    public function searchDiagnostics(): array
    {
        return $this->diagnostics !== [] ? $this->diagnostics : $this->inner->searchDiagnostics();
    }
}
