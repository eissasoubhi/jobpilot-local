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

final class BrowserAwareCustomScraperJobConnector implements GovernedJobSourceConnector, VersionedJobSourceConnector, SearchDiagnosticsConnector, ScheduledJobSourceConnector
{
    private AiRecoveryCustomScraperJobConnector $inner;

    /** @var array<string, mixed> */
    private array $diagnostics = [];

    public function __construct(
        private CustomScraperSource $source,
        CustomScraperExtractionService $extraction,
        CustomScraperAiRecoveryService $aiRecovery,
        private CustomScraperBrowserRecoveryService $browserRecovery,
    ) {
        $this->inner = new AiRecoveryCustomScraperJobConnector($source, $extraction, $aiRecovery);
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
        return ($this->data()['mode'] ?? CustomScraperSource::MODE_AUTO) === CustomScraperSource::MODE_BROWSER
            ? ConnectorMode::SCRAPING_BROWSER
            : $this->inner->mode();
    }

    public function parserVersion(): string
    {
        return 'custom-generic-html-v4-browser';
    }

    public function syncIntervalSeconds(): int
    {
        return $this->inner->syncIntervalSeconds();
    }

    public function isConfigured(): bool
    {
        $data = $this->data();
        if (($data['enabled'] ?? false) !== true || ($data['authorizationConfirmed'] ?? false) !== true) {
            return false;
        }

        if (($data['mode'] ?? CustomScraperSource::MODE_AUTO) === CustomScraperSource::MODE_BROWSER) {
            return $this->browserRecovery->isConfigured();
        }

        return $this->inner->isConfigured();
    }

    public function configurationMessage(): ?string
    {
        $data = $this->data();
        if (($data['mode'] ?? CustomScraperSource::MODE_AUTO) === CustomScraperSource::MODE_BROWSER) {
            return $this->browserRecovery->isConfigured()
                ? 'Rendu Browser/Playwright isolé activé : préflight HTTP/robots obligatoire, navigation read-only et extraction soumise au garde-fou qualité.'
                : 'Cette source nécessite Browser/Playwright mais le worker isolé n’est pas configuré.';
        }

        $message = $this->inner->configurationMessage();
        if (($data['mode'] ?? CustomScraperSource::MODE_AUTO) === CustomScraperSource::MODE_AUTO && $this->browserRecovery->isConfigured()) {
            return trim((string) $message).' Browser peut prendre le relais uniquement si le diagnostic HTTP le demande.';
        }

        return $message;
    }

    public function policy(): ConnectorPolicy
    {
        return $this->inner->policy();
    }

    public function search(array $targetJobs, array $skills): array
    {
        $mode = (string) ($this->data()['mode'] ?? CustomScraperSource::MODE_AUTO);
        if ($mode === CustomScraperSource::MODE_BROWSER) {
            if (!$this->isConfigured()) {
                $this->diagnostics = [
                    'browserRecovery' => [
                        'attempted' => false,
                        'stopReason' => 'BROWSER_WORKER_NOT_CONFIGURED',
                    ],
                ];
                return [];
            }

            $browser = $this->browserRecovery->recover($this->source, $targetJobs, $skills);
            $this->diagnostics = [
                'browserRecovery' => is_array($browser['diagnostics'] ?? null) ? $browser['diagnostics'] : [],
            ];
            return array_values(array_filter(is_array($browser['offers'] ?? null) ? $browser['offers'] : [], 'is_array'));
        }

        $offers = $this->inner->search($targetJobs, $skills);
        $baseDiagnostics = $this->inner->searchDiagnostics();
        if ($offers !== [] || $mode === CustomScraperSource::MODE_HTTP || !($baseDiagnostics['requiresBrowser'] ?? false)) {
            $this->diagnostics = [
                ...$baseDiagnostics,
                'browserRecovery' => [
                    'attempted' => false,
                    'stopReason' => $offers !== []
                        ? 'OFFERS_ALREADY_AVAILABLE'
                        : ($mode === CustomScraperSource::MODE_HTTP ? 'HTTP_FORCED' : 'BROWSER_NOT_REQUIRED'),
                ],
            ];
            return $offers;
        }

        $browser = $this->browserRecovery->recover($this->source, $targetJobs, $skills);
        $browserOffers = is_array($browser['offers'] ?? null) ? $browser['offers'] : [];
        $this->diagnostics = [
            ...$baseDiagnostics,
            'browserRecovery' => is_array($browser['diagnostics'] ?? null) ? $browser['diagnostics'] : [],
        ];

        return array_values(array_filter($browserOffers, 'is_array'));
    }

    public function searchDiagnostics(): array
    {
        return $this->diagnostics !== [] ? $this->diagnostics : $this->inner->searchDiagnostics();
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        return $this->source->toArray();
    }
}
