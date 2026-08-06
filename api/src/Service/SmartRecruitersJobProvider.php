<?php

declare(strict_types=1);

namespace App\Service;

use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use App\JobDiscovery\Domain\Connector\VersionedJobSourceConnector;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SmartRecruitersJobProvider implements GovernedJobSourceConnector, VersionedJobSourceConnector
{
    private const API_ENDPOINT = 'https://api.smartrecruiters.com/v1/companies';
    private const MAX_COMPANIES = 5;
    private const MAX_PAGES = 2;
    private const MAX_RESULTS_PER_PAGE = 100;
    private const MAX_DETAILS = 20;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiToken = '',
        private string $companyIdentifiers = '',
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
        return '2026-08-06.1';
    }

    public function policy(): ConnectorPolicy
    {
        $listRequests = max(1, count($this->companies())) * $this->boundedPages();

        return new ConnectorPolicy(
            ConnectorComplianceStatus::AUTHORIZED_ONLY,
            new \DateTimeImmutable('2026-08-06'),
            'Posting API officielle avec clé API et identifiants d’entreprises fournis explicitement par l’utilisateur.',
            maxRequestsPerSync: $listRequests + $this->boundedMaxDetails(),
        );
    }

    public function isConfigured(): bool
    {
        return trim($this->apiToken) !== '' && $this->companies() !== [];
    }

    public function configurationMessage(): ?string
    {
        if (trim($this->apiToken) === '' && $this->companies() === []) {
            return 'Renseigne SMARTRECRUITERS_API_TOKEN et SMARTRECRUITERS_COMPANY_IDENTIFIERS dans le fichier .env.';
        }
        if (trim($this->apiToken) === '') {
            return 'Renseigne SMARTRECRUITERS_API_TOKEN dans le fichier .env.';
        }
        if ($this->companies() === []) {
            return 'Renseigne au moins un identifiant valide dans SMARTRECRUITERS_COMPANY_IDENTIFIERS.';
        }

        return null;
    }

    public function search(array $targetJobs, array $skills): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $offers = [];
        $seen = [];
        $detailsFetched = 0;
        $limit = $this->boundedResultsPerPage();

        foreach ($this->companies() as $companyIdentifier) {
            for ($page = 0; $page < $this->boundedPages(); ++$page) {
                $response = $this->httpClient->request(
                    'GET',
                    sprintf('%s/%s/postings', self::API_ENDPOINT, rawurlencode($companyIdentifier)),
                    [
                        'headers' => $this->headers(),
                        'query' => [
                            'destination' => 'PUBLIC',
                            'limit' => $limit,
                            'offset' => $page * $limit,
                        ],
                        'timeout' => 10,
                    ],
                );

                $this->assertSuccessful($response->getStatusCode(), 'liste des offres');
                $payload = $response->toArray(false);
                $postings = is_array($payload['content'] ?? null) ? $payload['content'] : [];

                foreach ($postings as $posting) {
                    if (!is_array($posting) || !$this->matchesProfile($posting, $targetJobs, $skills)) {
                        continue;
                    }

                    $externalId = $this->externalId($posting);
                    if ($externalId === '' || isset($seen[$externalId]) || $detailsFetched >= $this->boundedMaxDetails()) {
                        continue;
                    }

                    $seen[$externalId] = true;
                    ++$detailsFetched;
                    $detail = $this->fetchDetail($companyIdentifier, $externalId);
                    $offers[] = $this->normalize($posting, $detail);
                }

                $count = count($postings);
                $totalFound = is_numeric($payload['totalFound'] ?? null) ? (int) $payload['totalFound'] : null;
                if (
                    $count === 0
                    || $count < $limit
                    || ($totalFound !== null && (($page * $limit) + $count) >= $totalFound)
                    || $detailsFetched >= $this->boundedMaxDetails()
                ) {
                    break;
                }
            }

            if ($detailsFetched >= $this->boundedMaxDetails()) {
                break;
            }
        }

        return $offers;
    }

    /** @return list<string> */
    private function companies(): array
    {
        $companies = [];
        foreach (preg_split('/[\s,;]+/', trim($this->companyIdentifiers)) ?: [] as $company) {
            $company = trim($company);
            if ($company === '' || preg_match('/^[a-z0-9][a-z0-9._-]{0,99}$/i', $company) !== 1) {
                continue;
            }

            $key = strtolower($company);
            if (!isset($companies[$key])) {
                $companies[$key] = $company;
            }
        }

        return array_slice(array_values($companies), 0, self::MAX_COMPANIES);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'Accept-Language' => 'fr',
            'X-SmartToken' => trim($this->apiToken),
        ];
    }

    /** @return array<string, mixed> */
    private function fetchDetail(string $companyIdentifier, string $externalId): array
    {
        $response = $this->httpClient->request(
            'GET',
            sprintf(
                '%s/%s/postings/%s',
                self::API_ENDPOINT,
                rawurlencode($companyIdentifier),
                rawurlencode($externalId),
            ),
            [
                'headers' => $this->headers(),
                'timeout' => 10,
            ],
        );

        if ($response->getStatusCode() === 404) {
            return [];
        }

        $this->assertSuccessful($response->getStatusCode(), 'détail d’une offre');
        $payload = $response->toArray(false);

        return is_array($payload) ? $payload : [];
    }

    private function assertSuccessful(int $statusCode, string $operation): void
    {
        if ($statusCode !== 200) {
            throw new \RuntimeException(sprintf(
                'SmartRecruiters a répondu avec le statut HTTP %d pendant la récupération de %s.',
                $statusCode,
                $operation,
            ));
        }
    }

    /** @param array<string, mixed> $posting */
    private function matchesProfile(array $posting, array $targetJobs, array $skills): bool
    {
        $haystack = $this->normalizeText(implode(' ', [
            (string) ($posting['name'] ?? ''),
            $this->nestedLabel($posting, 'department'),
            $this->nestedLabel($posting, 'function'),
            $this->nestedLabel($posting, 'industry'),
            $this->nestedLabel($posting, 'typeOfEmployment'),
            $this->location($posting),
        ]));

        $needles = $this->profileNeedles($targetJobs, $skills);
        if ($needles === []) {
            return true;
        }

        foreach ($needles as $needle) {
            if ($this->containsTerm($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $targetJobs
     * @param list<string> $skills
     * @return list<string>
     */
    private function profileNeedles(array $targetJobs, array $skills): array
    {
        $ignored = [
            'senior', 'developer', 'developpeur', 'engineer', 'full', 'stack', 'backend',
            'frontend', 'native', 'docker', 'mysql', 'postgresql', 'jenkins', 'redis',
            'gitlab', 'api', 'javascript',
        ];
        $needles = [];

        foreach (array_merge($targetJobs, $skills) as $value) {
            foreach (preg_split('/[^a-z0-9.+#]+/', $this->normalizeText((string) $value)) ?: [] as $token) {
                if (strlen($token) < 3 || in_array($token, $ignored, true)) {
                    continue;
                }
                $needles[$token] = true;
            }
        }

        return array_keys($needles);
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<string, mixed> $detail
     * @return array<string, mixed>
     */
    private function normalize(array $summary, array $detail): array
    {
        $source = $detail !== [] ? $detail : $summary;
        $title = $this->clean((string) ($source['name'] ?? $summary['name'] ?? ''));
        $description = $this->description($source);
        $employmentType = $this->nestedLabel($source, 'typeOfEmployment');
        if ($employmentType === '') {
            $employmentType = $this->nestedLabel($summary, 'typeOfEmployment');
        }
        $combined = $title.' '.$description.' '.$employmentType;
        $locationData = is_array($source['location'] ?? null)
            ? $source['location']
            : (is_array($summary['location'] ?? null) ? $summary['location'] : []);
        $remote = (bool) ($locationData['remote'] ?? false);
        $postingUrl = trim((string) ($source['postingUrl'] ?? $source['applyUrl'] ?? ''));

        return [
            'source' => $this->name(),
            'sourceUrl' => $postingUrl !== '' ? $postingUrl : null,
            'externalId' => $this->externalId($summary),
            'title' => $title,
            'company' => $this->nestedValue($source, 'company', 'name')
                ?: $this->nestedValue($summary, 'company', 'name'),
            'location' => $this->location($source !== [] ? $source : $summary),
            'contractType' => $this->contractType($employmentType, $combined),
            'workMode' => $remote ? 'Télétravail' : $this->workMode($combined),
            'description' => $description !== '' ? $description : $title,
            'publishedAt' => $this->date((string) ($source['releasedDate'] ?? $summary['releasedDate'] ?? '')),
            'salaryMin' => null,
            'salaryMax' => null,
            'rawData' => [
                'summary' => $summary,
                'detail' => $detail,
            ],
        ];
    }

    /** @param array<string, mixed> $posting */
    private function externalId(array $posting): string
    {
        return trim((string) ($posting['uuid'] ?? $posting['id'] ?? ''));
    }

    /** @param array<string, mixed> $posting */
    private function description(array $posting): string
    {
        $sections = is_array($posting['jobAd']['sections'] ?? null) ? $posting['jobAd']['sections'] : [];
        $parts = [];
        foreach (['jobDescription', 'qualifications', 'additionalInformation'] as $sectionName) {
            $section = is_array($sections[$sectionName] ?? null) ? $sections[$sectionName] : [];
            $text = $this->clean((string) ($section['text'] ?? ''));
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', array_values(array_unique($parts)));
    }

    /** @param array<string, mixed> $posting */
    private function location(array $posting): string
    {
        $location = is_array($posting['location'] ?? null) ? $posting['location'] : [];
        $parts = [];
        foreach (['city', 'region', 'country'] as $key) {
            $part = $this->clean((string) ($location[$key] ?? ''));
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        if ($parts === [] && (bool) ($location['remote'] ?? false)) {
            return 'Télétravail';
        }

        return implode(', ', array_values(array_unique($parts)));
    }

    /** @param array<string, mixed> $posting */
    private function nestedLabel(array $posting, string $key): string
    {
        return $this->nestedValue($posting, $key, 'label');
    }

    /** @param array<string, mixed> $posting */
    private function nestedValue(array $posting, string $key, string $valueKey): string
    {
        $value = is_array($posting[$key] ?? null) ? $posting[$key] : [];

        return $this->clean((string) ($value[$valueKey] ?? ''));
    }

    private function contractType(string $advertisedType, string $fallbackText): string
    {
        if (preg_match('/freelance|contractor|self[ -]?employed|independant|indépendant/i', $advertisedType) === 1) {
            return 'Freelance';
        }
        if (preg_match('/fixed[ -]?term|temporary|contract|cdd/i', $advertisedType) === 1) {
            return 'CDD';
        }
        if (preg_match('/intern|stage|alternance|apprentice/i', $advertisedType) === 1) {
            return 'Stage';
        }
        if (preg_match('/permanent|full[ -]?time|cdi/i', $advertisedType) === 1) {
            return 'CDI';
        }

        if (preg_match('/freelance|contractor|independant|indépendant/i', $fallbackText) === 1) {
            return 'Freelance';
        }
        if (preg_match('/\bcdd\b|fixed[ -]?term|temporary/i', $fallbackText) === 1) {
            return 'CDD';
        }

        return 'CDI';
    }

    private function workMode(string $text): string
    {
        if (preg_match('/hybrid|hybride/i', $text) === 1) {
            return 'Hybride';
        }
        if (preg_match('/remote|teletravail|télétravail|home ?office/i', $text) === 1) {
            return 'Télétravail';
        }

        return 'Sur site';
    }

    private function date(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (\Exception) {
            return null;
        }
    }

    private function containsTerm(string $haystack, string $needle): bool
    {
        return preg_match(
            '/(?<![a-z0-9])'.preg_quote($needle, '/').'(?![a-z0-9])/',
            $haystack,
        ) === 1;
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function normalizeText(string $value): string
    {
        $clean = $this->clean($value);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean);

        return strtolower($transliterated === false ? $clean : $transliterated);
    }

    private function boundedPages(): int
    {
        return max(1, min(self::MAX_PAGES, $this->pages));
    }

    private function boundedResultsPerPage(): int
    {
        return max(1, min(self::MAX_RESULTS_PER_PAGE, $this->resultsPerPage));
    }

    private function boundedMaxDetails(): int
    {
        return max(1, min(self::MAX_DETAILS, $this->maxDetails));
    }
}
