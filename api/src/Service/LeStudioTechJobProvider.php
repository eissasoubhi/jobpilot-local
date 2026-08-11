<?php

declare(strict_types=1);

namespace App\Service;

use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use App\JobDiscovery\Domain\Connector\JobSourceConnector;
use App\JobDiscovery\Domain\Connector\VersionedJobSourceConnector;
use App\JobDiscovery\Infrastructure\Scraping\Html\LeStudioTechMissionParser;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingRequest;

final class LeStudioTechJobProvider implements JobSourceConnector, GovernedJobSourceConnector, VersionedJobSourceConnector
{
    private const LISTING_URL = 'https://app.lestudiotech.com/freelances/missions';
    private const HARD_MAX_PAGES = 10;
    private const HARD_MAX_DETAILS = 30;

    public function __construct(
        private ControlledHttpScrapingClient $httpClient,
        private LeStudioTechMissionParser $parser,
        private bool $enabled = true,
        private int $pages = 8,
        private int $maxDetails = 20,
    ) {
    }

    public function code(): string
    {
        return 'le-studio-tech';
    }

    public function name(): string
    {
        return 'Le Studio Tech';
    }

    public function mode(): ConnectorMode
    {
        return ConnectorMode::SCRAPING_HTTP;
    }

    public function parserVersion(): string
    {
        return 'le-studio-tech-html-v1';
    }

    public function isConfigured(): bool
    {
        return $this->enabled;
    }

    public function configurationMessage(): ?string
    {
        if (!$this->enabled) {
            return 'Le connecteur est désactivé par LE_STUDIO_TECH_ENABLED.';
        }

        return 'Missions publiques sans session ; collecte HTTP bornée avec contrôle robots.txt obligatoire.';
    }

    public function policy(): ConnectorPolicy
    {
        return new ConnectorPolicy(
            ConnectorComplianceStatus::ALLOWED,
            new \DateTimeImmutable('2026-08-09'),
            'Pages de missions publiques sans authentification. Usage JobPilot limité à la recherche personnelle : aucun login, aucune candidature automatique, aucune republication de la base et contrôle robots.txt avant collecte.',
            maxRequestsPerSync: 60,
            dailyQuota: 240,
            minimumDelayMilliseconds: 1_200,
            respectsRobotsTxt: true,
        );
    }

    /**
     * Probe réseau réel strictement borné pour le workflow de smoke test séparé de la CI.
     *
     * Aucune offre n'est persistée : une page liste est lue et, si possible, une seule
     * fiche détail est validée avec le même transport contrôlé et le même parseur.
     *
     * @return array{
     *   result: 'PASS'|'WARN',
     *   source: string,
     *   mode: string,
     *   parserVersion: string,
     *   statusCode: int,
     *   finalHost: string,
     *   candidateCount: int,
     *   detailChecked: bool,
     *   detailStatusCode: int|null,
     *   detailExtracted: bool|null,
     *   durationMs: int
     * }
     */
    public function smokeTest(): array
    {
        if (!$this->enabled) {
            throw new \LogicException('Le connecteur Le Studio Tech est désactivé.');
        }

        $startedAt = microtime(true);
        $listing = $this->httpClient->fetch(new HttpScrapingRequest(
            $this->code(),
            self::LISTING_URL,
            $this->policy(),
            timeoutSeconds: 10,
            maxRetries: 0,
            initialBackoffMilliseconds: 0,
            maxResponseBytes: 3_000_000,
        ));
        $offers = $this->parser->parseListing($listing->body, self::LISTING_URL);

        $detailChecked = false;
        $detailStatusCode = null;
        $detailExtracted = null;
        $firstOffer = $offers[0] ?? null;
        if (is_array($firstOffer)) {
            $detailUrl = trim((string) ($firstOffer['sourceUrl'] ?? ''));
            if ($detailUrl !== '') {
                $detailChecked = true;
                $detail = $this->httpClient->fetch(new HttpScrapingRequest(
                    $this->code(),
                    $detailUrl,
                    $this->policy(),
                    timeoutSeconds: 10,
                    maxRetries: 0,
                    initialBackoffMilliseconds: 0,
                    maxResponseBytes: 3_000_000,
                ));
                $detailStatusCode = $detail->statusCode;
                $detailExtracted = $this->parser->enrichDetail($detail->body, $firstOffer) !== null;
            }
        }

        $host = strtolower((string) (parse_url($listing->url, PHP_URL_HOST) ?: ''));

        return [
            'result' => $offers === [] ? 'WARN' : 'PASS',
            'source' => $this->name(),
            'mode' => $this->mode()->value,
            'parserVersion' => $this->parserVersion(),
            'statusCode' => $listing->statusCode,
            'finalHost' => $host,
            'candidateCount' => count($offers),
            'detailChecked' => $detailChecked,
            'detailStatusCode' => $detailStatusCode,
            'detailExtracted' => $detailExtracted,
            'durationMs' => max(0, (int) round((microtime(true) - $startedAt) * 1000)),
        ];
    }

    public function search(array $targetJobs, array $skills): array
    {
        if (!$this->enabled) {
            return [];
        }

        $pageLimit = max(1, min(self::HARD_MAX_PAGES, $this->pages));
        $detailLimit = max(0, min(self::HARD_MAX_DETAILS, $this->maxDetails));
        $offers = [];

        for ($page = 1; $page <= $pageLimit; ++$page) {
            $url = $page === 1 ? self::LISTING_URL : self::LISTING_URL.'?page='.$page;
            $response = $this->httpClient->fetch(new HttpScrapingRequest(
                $this->code(),
                $url,
                $this->policy(),
                timeoutSeconds: 10,
                maxRetries: 1,
                initialBackoffMilliseconds: 500,
                maxResponseBytes: 3_000_000,
            ));
            $pageOffers = $this->parser->parseListing($response->body, self::LISTING_URL);
            if ($pageOffers === []) {
                break;
            }

            foreach ($pageOffers as $offer) {
                $externalId = trim((string) ($offer['externalId'] ?? ''));
                if ($externalId === '' || isset($offers[$externalId])) {
                    continue;
                }
                $offers[$externalId] = $offer;
            }
        }

        if ($detailLimit === 0 || $offers === []) {
            return array_values($offers);
        }

        $profileTerms = $this->profileTerms($targetJobs, $skills);
        $enriched = 0;

        foreach ($offers as $externalId => $offer) {
            if ($enriched >= $detailLimit || !$this->shouldEnrich($offer, $profileTerms)) {
                continue;
            }

            $detailUrl = trim((string) ($offer['sourceUrl'] ?? ''));
            if ($detailUrl === '') {
                continue;
            }

            $response = $this->httpClient->fetch(new HttpScrapingRequest(
                $this->code(),
                $detailUrl,
                $this->policy(),
                timeoutSeconds: 10,
                maxRetries: 1,
                initialBackoffMilliseconds: 500,
                maxResponseBytes: 3_000_000,
            ));
            ++$enriched;

            $detail = $this->parser->enrichDetail($response->body, $offer);
            if ($detail === null) {
                unset($offers[$externalId]);
                continue;
            }
            $offers[$externalId] = $detail;
        }

        return array_values($offers);
    }

    /**
     * @param list<string> $targetJobs
     * @param list<string> $skills
     * @return list<string>
     */
    private function profileTerms(array $targetJobs, array $skills): array
    {
        $terms = [];
        $ignored = [
            'senior', 'junior', 'lead', 'h', 'f', 'docker', 'mysql', 'postgresql', 'redis',
            'jenkins', 'gitlab', 'api', 'javascript', 'typescript', 'web',
        ];

        foreach (array_merge($targetJobs, $skills) as $value) {
            foreach (preg_split('/[^a-z0-9.+#]+/', $this->normalize((string) $value)) ?: [] as $token) {
                if (strlen($token) < 3 || in_array($token, $ignored, true)) {
                    continue;
                }
                $terms[$token] = true;
            }
        }

        return array_keys($terms);
    }

    /** @param array<string, mixed> $offer @param list<string> $profileTerms */
    private function shouldEnrich(array $offer, array $profileTerms): bool
    {
        if ($profileTerms === []) {
            return true;
        }

        $title = $this->normalize((string) ($offer['title'] ?? ''));
        foreach ($profileTerms as $term) {
            if ($this->containsTerm($title, $term)) {
                return true;
            }
        }

        return false;
    }

    private function containsTerm(string $haystack, string $needle): bool
    {
        return preg_match('/(?<![a-z0-9])'.preg_quote($needle, '/').'(?![a-z0-9])/', $haystack) === 1;
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return strtolower($ascii === false ? $value : $ascii);
    }
}
