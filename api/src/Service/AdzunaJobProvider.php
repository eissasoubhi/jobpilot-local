<?php

declare(strict_types=1);

namespace App\Service;

use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AdzunaJobProvider implements GovernedJobSourceConnector
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $appId = '',
        private string $appKey = '',
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
        return new ConnectorPolicy(
            ConnectorComplianceStatus::AUTHORIZED_ONLY,
            new \DateTimeImmutable('2026-08-05'),
            'API officielle nécessitant des identifiants développeur fournis par l’utilisateur.',
            maxRequestsPerSync: 6,
        );
    }

    public function isConfigured(): bool
    {
        return trim($this->appId) !== '' && trim($this->appKey) !== '';
    }

    public function configurationMessage(): ?string
    {
        return $this->isConfigured()
            ? null
            : 'Renseigne ADZUNA_APP_ID et ADZUNA_APP_KEY dans le fichier .env.';
    }

    public function search(array $targetJobs, array $skills): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $offers = [];
        $seen = [];

        foreach ($this->queries($targetJobs, $skills) as $query) {
            $parameters = [
                'app_id' => $this->appId,
                'app_key' => $this->appKey,
                'results_per_page' => max(1, min(50, $this->resultsPerQuery)),
                'what' => $query,
                'sort_by' => 'date',
                'content-type' => 'application/json',
            ];
            if (trim($this->where) !== '') {
                $parameters['where'] = trim($this->where);
            }

            $url = sprintf(
                'https://api.adzuna.com/v1/api/jobs/%s/search/1',
                rawurlencode(strtolower(trim($this->country) ?: 'fr')),
            );
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['Accept' => 'application/json'],
                'query' => $parameters,
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException(sprintf('Adzuna a répondu avec le statut HTTP %d.', $response->getStatusCode()));
            }

            $payload = $response->toArray(false);
            foreach (is_array($payload['results'] ?? null) ? $payload['results'] : [] as $job) {
                if (!is_array($job)) {
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
        }

        return $offers;
    }

    /**
     * @param list<string> $targetJobs
     * @param list<string> $skills
     * @return list<string>
     */
    private function queries(array $targetJobs, array $skills): array
    {
        $queries = [];
        foreach ($targetJobs as $targetJob) {
            $query = preg_replace('/\b(senior|developer|developpeur|engineer|native)\b/i', ' ', (string) $targetJob) ?? '';
            $query = trim(preg_replace('/[^a-zA-Z0-9.+#]+/', ' ', $query) ?? '');
            if (strlen($query) >= 3) {
                $queries[strtolower($query)] = $query;
            }
        }

        if ($queries === []) {
            foreach ($skills as $skill) {
                $skill = trim((string) $skill);
                if (strlen($skill) >= 3) {
                    $queries[strtolower($skill)] = $skill;
                }
            }
        }

        return array_slice(array_values($queries), 0, 6);
    }

    /** @param array<string, mixed> $job */
    private function normalize(array $job): array
    {
        $title = $this->clean((string) ($job['title'] ?? ''));
        $description = $this->clean((string) ($job['description'] ?? ''));
        $url = trim((string) ($job['redirect_url'] ?? ''));
        $externalId = trim((string) ($job['id'] ?? ''));
        if ($externalId === '') {
            $externalId = hash('sha256', $url.'|'.$title);
        }

        $location = is_array($job['location'] ?? null)
            ? $this->clean((string) ($job['location']['display_name'] ?? ''))
            : '';
        $company = is_array($job['company'] ?? null)
            ? $this->clean((string) ($job['company']['display_name'] ?? ''))
            : '';
        $advertisedContractType = $this->clean((string) ($job['contract_type'] ?? ''));
        $combined = $title.' '.$description;

        return [
            'source' => $this->name(),
            'sourceUrl' => $url !== '' ? $url : null,
            'externalId' => $externalId,
            'title' => $title,
            'company' => $company,
            'location' => $location,
            'contractType' => $this->contractType($advertisedContractType, $combined),
            'workMode' => $this->workMode($combined),
            'description' => $description !== '' ? $description : $title,
            'publishedAt' => $this->date((string) ($job['created'] ?? '')),
            'salaryMin' => $this->integerOrNull($job['salary_min'] ?? null),
            'salaryMax' => $this->integerOrNull($job['salary_max'] ?? null),
            'rawData' => $job,
        ];
    }

    private function contractType(string $advertisedType, string $fallbackText): string
    {
        if ($advertisedType !== '') {
            if (preg_match('/freelance|contractor|self[ -]?employed/i', $advertisedType) === 1) {
                return 'Freelance';
            }
            if (preg_match('/permanent|full[ -]?time|cdi/i', $advertisedType) === 1) {
                return 'CDI';
            }
            if (preg_match('/contract|temporary|fixed[ -]?term|cdd/i', $advertisedType) === 1) {
                return 'CDD';
            }
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
        if (preg_match('/remote|teletravail|télétravail|home ?office/i', $text) === 1) {
            return 'Télétravail';
        }
        if (preg_match('/hybrid|hybride/i', $text) === 1) {
            return 'Hybride';
        }

        return 'Sur site';
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) round((float) $value)) : null;
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

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
