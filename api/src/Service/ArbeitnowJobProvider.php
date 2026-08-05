<?php

declare(strict_types=1);

namespace App\Service;

use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ArbeitnowJobProvider implements GovernedJobSourceConnector
{
    private const ENDPOINT = 'https://www.arbeitnow.com/api/job-board-api';

    public function __construct(
        private HttpClientInterface $httpClient,
        private bool $enabled = true,
        private int $pages = 3,
    ) {
    }

    public function code(): string
    {
        return 'arbeitnow';
    }

    public function name(): string
    {
        return 'Arbeitnow';
    }

    public function mode(): ConnectorMode
    {
        return ConnectorMode::API;
    }

    public function policy(): ConnectorPolicy
    {
        return new ConnectorPolicy(
            ConnectorComplianceStatus::ALLOWED,
            new \DateTimeImmutable('2026-08-05'),
            'API publique sans authentification. Les limites locales restent volontairement inférieures au maximum technique.',
            maxRequestsPerSync: max(1, min(5, $this->pages)),
        );
    }

    public function isConfigured(): bool
    {
        return $this->enabled;
    }

    public function configurationMessage(): ?string
    {
        return $this->enabled ? null : 'Le connecteur est désactivé par ARBEITNOW_ENABLED.';
    }

    public function search(array $targetJobs, array $skills): array
    {
        if (!$this->enabled) {
            return [];
        }

        $offers = [];
        $seen = [];
        $pages = max(1, min(5, $this->pages));

        for ($page = 1; $page <= $pages; ++$page) {
            $response = $this->httpClient->request('GET', self::ENDPOINT, [
                'headers' => ['Accept' => 'application/json'],
                'query' => ['page' => $page],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException(sprintf('Arbeitnow a répondu avec le statut HTTP %d.', $response->getStatusCode()));
            }

            $payload = $response->toArray(false);
            $jobs = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            foreach ($jobs as $job) {
                if (!is_array($job) || !$this->matchesProfile($job, $targetJobs, $skills)) {
                    continue;
                }

                $normalized = $this->normalize($job);
                $externalId = (string) ($normalized['externalId'] ?? '');
                if ($externalId === '' || isset($seen[$externalId])) {
                    continue;
                }

                $seen[$externalId] = true;
                $offers[] = $normalized;
            }

            $next = $payload['links']['next'] ?? null;
            if ($next === null || $next === '') {
                break;
            }
        }

        return $offers;
    }

    /** @param array<string, mixed> $job */
    private function matchesProfile(array $job, array $targetJobs, array $skills): bool
    {
        $tags = is_array($job['tags'] ?? null) ? implode(' ', array_map('strval', $job['tags'])) : '';
        $haystack = $this->normalizeText(implode(' ', [
            (string) ($job['title'] ?? ''),
            (string) ($job['description'] ?? ''),
            $tags,
        ]));

        $needles = $this->profileNeedles($targetJobs, $skills);
        $matchesTechnology = $needles === [];
        foreach ($needles as $needle) {
            if ($this->containsTerm($haystack, $needle)) {
                $matchesTechnology = true;
                break;
            }
        }

        if (!$matchesTechnology) {
            return false;
        }

        if ((bool) ($job['remote'] ?? false)) {
            return true;
        }

        $location = $this->normalizeText((string) ($job['location'] ?? ''));

        return preg_match(
            '/\b(france|paris|lyon|lille|nantes|bordeaux|toulouse|marseille|nice|rennes|montpellier|strasbourg|grenoble|rouen|cergy|versailles|boulogne|nanterre|saint-denis|ile de france)\b/',
            $location,
        ) === 1;
    }

    /**
     * @param list<string> $targetJobs
     * @param list<string> $skills
     * @return list<string>
     */
    private function profileNeedles(array $targetJobs, array $skills): array
    {
        $values = array_merge($targetJobs, $skills);
        $ignored = [
            'senior', 'developer', 'developpeur', 'engineer', 'full', 'stack', 'backend',
            'frontend', 'native', 'docker', 'mysql', 'postgresql', 'jenkins', 'redis',
            'gitlab', 'api', 'javascript',
        ];
        $needles = [];

        foreach ($values as $value) {
            foreach (preg_split('/[^a-z0-9.+#]+/', $this->normalizeText((string) $value)) ?: [] as $token) {
                if (strlen($token) < 3 || in_array($token, $ignored, true)) {
                    continue;
                }
                $needles[$token] = true;
            }
        }

        return array_keys($needles);
    }

    /** @param array<string, mixed> $job */
    private function normalize(array $job): array
    {
        $title = $this->clean((string) ($job['title'] ?? ''));
        $description = $this->clean((string) ($job['description'] ?? ''));
        $url = trim((string) ($job['url'] ?? ''));
        $slug = trim((string) ($job['slug'] ?? ''));
        $externalId = $slug !== '' ? $slug : hash('sha256', $url.'|'.$title);
        $remote = (bool) ($job['remote'] ?? false);
        $jobTypes = is_array($job['job_types'] ?? null) ? array_map('strval', $job['job_types']) : [];
        $combined = $title.' '.$description;

        return [
            'source' => $this->name(),
            'sourceUrl' => $url !== '' ? $url : null,
            'externalId' => $externalId,
            'title' => $title,
            'company' => $this->clean((string) ($job['company_name'] ?? '')),
            'location' => $this->clean((string) ($job['location'] ?? ($remote ? 'Télétravail' : ''))),
            'contractType' => $this->contractType($jobTypes, $title),
            'workMode' => $remote ? 'Télétravail' : $this->workMode($combined),
            'description' => $description !== '' ? $description : $title,
            'publishedAt' => $this->publishedAt($job['created_at'] ?? null),
            'salaryMin' => null,
            'salaryMax' => null,
            'rawData' => $job,
        ];
    }

    /** @param list<string> $jobTypes */
    private function contractType(array $jobTypes, string $fallbackTitle): string
    {
        $advertisedTypes = implode(' ', $jobTypes);
        if (preg_match('/freelance|contractor|self[ -]?employed/i', $advertisedTypes) === 1) {
            return 'Freelance';
        }
        if (preg_match('/full[ _-]?time|permanent|cdi/i', $advertisedTypes) === 1) {
            return 'CDI';
        }
        if (preg_match('/fixed[ _-]?term|temporary|cdd|contract/i', $advertisedTypes) === 1) {
            return 'CDD';
        }
        if (preg_match('/freelance|contractor|independant|indépendant/i', $fallbackTitle) === 1) {
            return 'Freelance';
        }
        if (preg_match('/\bcdd\b|fixed[ -]?term|temporary/i', $fallbackTitle) === 1) {
            return 'CDD';
        }

        return 'CDI';
    }

    private function workMode(string $text): string
    {
        return preg_match('/hybrid|hybride/i', $text) === 1 ? 'Hybride' : 'Sur site';
    }

    private function publishedAt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (new \DateTimeImmutable())->setTimestamp((int) $value)->format(DATE_ATOM);
        }

        try {
            return (new \DateTimeImmutable((string) $value))->format(DATE_ATOM);
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
}
