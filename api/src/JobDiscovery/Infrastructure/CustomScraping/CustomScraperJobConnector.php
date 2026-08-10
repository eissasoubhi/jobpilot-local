<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\CustomScraping;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use App\JobDiscovery\Domain\Connector\SearchDiagnosticsConnector;
use App\JobDiscovery\Domain\Connector\VersionedJobSourceConnector;
use App\Service\CustomScraperExtractionService;

final class CustomScraperJobConnector implements GovernedJobSourceConnector, VersionedJobSourceConnector, SearchDiagnosticsConnector
{
    /** @var array<string, mixed> */
    private array $diagnostics = [];

    public function __construct(
        private CustomScraperSource $source,
        private CustomScraperExtractionService $extraction,
    ) {
        if (!is_int($this->data()['id'] ?? null)) {
            throw new \InvalidArgumentException('Une source de scraping persistée est obligatoire pour créer un connecteur dynamique.');
        }
    }

    public function code(): string
    {
        return 'custom-scraper-'.$this->data()['id'];
    }

    public function name(): string
    {
        return (string) ($this->data()['name'] ?? $this->code());
    }

    public function mode(): ConnectorMode
    {
        return ($this->data()['mode'] ?? CustomScraperSource::MODE_AUTO) === CustomScraperSource::MODE_BROWSER
            ? ConnectorMode::SCRAPING_BROWSER
            : ConnectorMode::SCRAPING_HTTP;
    }

    public function parserVersion(): string
    {
        return 'custom-generic-html-v1';
    }

    public function isConfigured(): bool
    {
        $data = $this->data();

        return ($data['enabled'] ?? false) === true
            && ($data['authorizationConfirmed'] ?? false) === true
            && ($data['mode'] ?? CustomScraperSource::MODE_AUTO) !== CustomScraperSource::MODE_BROWSER;
    }

    public function configurationMessage(): ?string
    {
        $data = $this->data();
        if (($data['enabled'] ?? false) !== true) {
            return 'La source personnalisée est désactivée.';
        }
        if (($data['authorizationConfirmed'] ?? false) !== true) {
            return 'La confirmation d’autorisation de collecte a été retirée.';
        }
        if (($data['mode'] ?? CustomScraperSource::MODE_AUTO) === CustomScraperSource::MODE_BROWSER) {
            return 'Cette source attend le worker Browser/Playwright isolé.';
        }

        return 'Scraping HTTP générique : une page de liste par cycle, fiches détail bornées et import limité aux extractions fiables.';
    }

    public function policy(): ConnectorPolicy
    {
        $data = $this->data();
        $checkedAt = is_string($data['authorizationCheckedAt'] ?? null)
            ? new \DateTimeImmutable((string) $data['authorizationCheckedAt'])
            : null;
        $maxDetails = min(10, max(0, (int) ($data['maxDetails'] ?? 0)));

        return new ConnectorPolicy(
            ($data['authorizationConfirmed'] ?? false) === true
                ? ConnectorComplianceStatus::ALLOWED
                : ConnectorComplianceStatus::AUTHORIZED_ONLY,
            $checkedAt,
            is_string($data['authorizationReference'] ?? null)
                ? $data['authorizationReference']
                : 'Autorisation de collecte confirmée par l’utilisateur.',
            maxRequestsPerSync: 1 + $maxDetails,
            dailyQuota: 100,
            minimumDelayMilliseconds: 1_000,
            respectsRobotsTxt: true,
        );
    }

    public function search(array $targetJobs, array $skills): array
    {
        if (!$this->isConfigured()) {
            $this->diagnostics = [
                'sourceId' => $this->data()['id'] ?? null,
                'reliableCount' => 0,
                'candidateCount' => 0,
                'requiresBrowser' => $this->mode() === ConnectorMode::SCRAPING_BROWSER,
                'skipped' => true,
                'reason' => $this->configurationMessage(),
            ];

            return [];
        }

        $preview = $this->extraction->preview($this->source);
        $candidates = is_array($preview['candidates'] ?? null) ? $preview['candidates'] : [];
        $reliable = array_values(array_filter(
            $candidates,
            static function (mixed $candidate): bool {
                if (!is_array($candidate)) {
                    return false;
                }
                $rawData = is_array($candidate['rawData'] ?? null) ? $candidate['rawData'] : [];
                $quality = is_array($rawData['quality'] ?? null) ? $rawData['quality'] : [];

                return ($quality['reliable'] ?? false) === true;
            },
        ));

        $this->diagnostics = [
            'sourceId' => $this->data()['id'] ?? null,
            'listingUrl' => $this->data()['listingUrl'] ?? null,
            'pagesFetched' => 1,
            'configuredMaxPages' => (int) ($this->data()['maxPages'] ?? 1),
            'paginationStrategy' => 'SINGLE_PAGE_UNTIL_GENERIC_PAGINATION_IS_DETECTED',
            'candidateCount' => count($candidates),
            'reliableCount' => count($reliable),
            'filteredByExtractionQuality' => max(0, count($candidates) - count($reliable)),
            'detailEnriched' => (int) ($preview['detailEnriched'] ?? 0),
            'detailLimit' => (int) ($preview['detailLimit'] ?? 0),
            'requiresBrowser' => (bool) ($preview['requiresBrowser'] ?? false),
            'detailError' => is_string($preview['detailError'] ?? null) ? $preview['detailError'] : null,
            'networkRequests' => (int) ($preview['http']['networkRequests'] ?? 0),
            'skipped' => false,
        ];

        return $reliable;
    }

    public function searchDiagnostics(): array
    {
        return $this->diagnostics;
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        return $this->source->toArray();
    }
}
