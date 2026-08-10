<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\CustomScraping;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use App\JobDiscovery\Domain\Connector\ScheduledJobSourceConnector;
use App\JobDiscovery\Domain\Connector\SearchDiagnosticsConnector;
use App\JobDiscovery\Domain\Connector\VersionedJobSourceConnector;
use App\Service\CustomScraperExtractionService;

final class CustomScraperJobConnector implements GovernedJobSourceConnector, VersionedJobSourceConnector, SearchDiagnosticsConnector, ScheduledJobSourceConnector
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
        return 'custom-generic-html-v2';
    }

    public function syncIntervalSeconds(): int
    {
        return max(3_600, (int) ($this->data()['syncIntervalMinutes'] ?? 360) * 60);
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

        return 'Scraping HTTP générique : pagination détectée du même domaine, fiches détail bornées et import limité aux extractions fiables.';
    }

    public function policy(): ConnectorPolicy
    {
        $data = $this->data();
        $checkedAt = is_string($data['authorizationCheckedAt'] ?? null)
            ? new \DateTimeImmutable((string) $data['authorizationCheckedAt'])
            : null;
        $maxPages = min(10, max(1, (int) ($data['maxPages'] ?? 1)));
        $maxDetails = min(30, max(0, (int) ($data['maxDetails'] ?? 0)));

        return new ConnectorPolicy(
            ($data['authorizationConfirmed'] ?? false) === true
                ? ConnectorComplianceStatus::ALLOWED
                : ConnectorComplianceStatus::AUTHORIZED_ONLY,
            $checkedAt,
            is_string($data['authorizationReference'] ?? null)
                ? $data['authorizationReference']
                : 'Autorisation de collecte confirmée par l’utilisateur.',
            maxRequestsPerSync: $maxPages + $maxDetails,
            dailyQuota: 300,
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

        $collection = $this->extraction->collect($this->source, $targetJobs, $skills);
        $candidates = is_array($collection['candidates'] ?? null) ? $collection['candidates'] : [];
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
        $pagination = is_array($collection['pagination'] ?? null) ? $collection['pagination'] : [];
        $nextPageUrl = is_string($pagination['nextUrl'] ?? null) ? $pagination['nextUrl'] : null;

        $this->diagnostics = [
            'sourceId' => $this->data()['id'] ?? null,
            'listingUrl' => $this->data()['listingUrl'] ?? null,
            'pagesFetched' => (int) ($pagination['pagesFetched'] ?? 0),
            'configuredMaxPages' => (int) ($this->data()['maxPages'] ?? 1),
            'effectivePageLimit' => (int) ($pagination['pageLimit'] ?? 1),
            'paginationStrategy' => 'SAFE_DETECTED_NEXT_CHAIN',
            'paginationStopReason' => is_string($pagination['stopReason'] ?? null) ? $pagination['stopReason'] : null,
            'paginationLoopDetected' => (bool) ($pagination['loopDetected'] ?? false),
            'paginationPageError' => is_string($pagination['pageError'] ?? null) ? $pagination['pageError'] : null,
            'nextPageDetected' => $nextPageUrl !== null,
            'nextPageUrl' => $nextPageUrl,
            'paginationDetectionStrategy' => is_string($pagination['strategy'] ?? null) ? $pagination['strategy'] : null,
            'paginationDetectionConfidence' => is_string($pagination['confidence'] ?? null) ? $pagination['confidence'] : null,
            'candidateCount' => count($candidates),
            'reliableCount' => count($reliable),
            'filteredByExtractionQuality' => max(0, count($candidates) - count($reliable)),
            'detailEnriched' => (int) ($collection['detailEnriched'] ?? 0),
            'detailLimit' => (int) ($collection['detailLimit'] ?? 0),
            'detailPriorityApplied' => (bool) ($collection['detailPriorityApplied'] ?? false),
            'requiresBrowser' => (bool) ($collection['requiresBrowser'] ?? false),
            'detailError' => is_string($collection['detailError'] ?? null) ? $collection['detailError'] : null,
            'networkRequests' => (int) ($collection['http']['networkRequests'] ?? 0),
            'syncIntervalSeconds' => $this->syncIntervalSeconds(),
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
