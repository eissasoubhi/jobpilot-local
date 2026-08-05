<?php

declare(strict_types=1);

namespace App\Service;

use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorMode;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use App\JobDiscovery\Domain\Connector\VersionedJobSourceConnector;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FranceTravailJobProvider implements GovernedJobSourceConnector, VersionedJobSourceConnector
{
    private const DEFAULT_TOKEN_ENDPOINT = 'https://entreprise.francetravail.fr/connexion/oauth2/access_token?realm=/partenaire';
    private const DEFAULT_SEARCH_ENDPOINT = 'https://api.francetravail.io/partenaire/offresdemploi/v2/offres/search';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $clientId = '',
        private string $clientSecret = '',
        private string $scope = 'api_offresdemploiv2 o2dsoffre',
        private string $tokenEndpoint = self::DEFAULT_TOKEN_ENDPOINT,
        private string $searchEndpoint = self::DEFAULT_SEARCH_ENDPOINT,
        private int $resultsPerQuery = 50,
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
        return 'offres-emploi-v2';
    }

    public function policy(): ConnectorPolicy
    {
        return new ConnectorPolicy(
            ConnectorComplianceStatus::AUTHORIZED_ONLY,
            new \DateTimeImmutable('2026-08-06'),
            'API officielle Offres d’emploi v2. La collecte nécessite les identifiants d’une application France Travail.io appartenant à l’utilisateur.',
            maxRequestsPerSync: 7,
        );
    }

    public function isConfigured(): bool
    {
        return trim($this->clientId) !== '' && trim($this->clientSecret) !== '';
    }

    public function configurationMessage(): ?string
    {
        return $this->isConfigured()
            ? null
            : 'Renseigne FRANCE_TRAVAIL_CLIENT_ID et FRANCE_TRAVAIL_CLIENT_SECRET dans le fichier .env.';
    }

    public function search(array $targetJobs, array $skills): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $token = $this->accessToken();
        $offers = [];
        $seen = [];

        foreach ($this->queries($targetJobs, $skills) as $query) {
            $limit = max(1, min(150, $this->resultsPerQuery));
            $response = $this->httpClient->request('GET', $this->searchEndpoint, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$token,
                    'Range' => sprintf('offres=0-%d', $limit - 1),
                ],
                'query' => [
                    'motsCles' => $query,
                    'sort' => 1,
                ],
                'timeout' => 15,
            ]);

            if (!in_array($response->getStatusCode(), [200, 206], true)) {
                throw new \RuntimeException(sprintf('France Travail a répondu avec le statut HTTP %d.', $response->getStatusCode()));
            }

            $payload = $response->toArray(false);
            foreach (is_array($payload['resultats'] ?? null) ? $payload['resultats'] : [] as $job) {
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

    private function accessToken(): string
    {
        $response = $this->httpClient->request('POST', $this->tokenEndpoint, [
            'headers' => ['Accept' => 'application/json'],
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => trim($this->scope),
            ],
            'timeout' => 10,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('L’authentification France Travail a répondu avec le statut HTTP %d.', $response->getStatusCode()));
        }

        $payload = $response->toArray(false);
        $token = trim((string) ($payload['access_token'] ?? ''));
        if ($token === '') {
            throw new \RuntimeException('L’authentification France Travail n’a retourné aucun jeton d’accès.');
        }

        return $token;
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
            $query = preg_replace('/\b(senior|developer|developpeur|développeur|engineer|native)\b/iu', ' ', (string) $targetJob) ?? '';
            $query = trim(preg_replace('/[^\pL\pN.+#]+/u', ' ', $query) ?? '');
            if (mb_strlen($query) >= 3) {
                $queries[mb_strtolower($query)] = $query;
            }
        }

        if ($queries === []) {
            foreach ($skills as $skill) {
                $skill = trim((string) $skill);
                if (mb_strlen($skill) >= 3) {
                    $queries[mb_strtolower($skill)] = $skill;
                }
            }
        }

        return array_slice(array_values($queries), 0, 6);
    }

    /** @param array<string, mixed> $job */
    private function normalize(array $job): array
    {
        $title = $this->clean((string) ($job['intitule'] ?? ''));
        $description = $this->clean((string) ($job['description'] ?? ''));
        $externalId = trim((string) ($job['id'] ?? ''));
        $origin = is_array($job['origineOffre'] ?? null) ? $job['origineOffre'] : [];
        $url = trim((string) ($origin['urlOrigine'] ?? ''));
        if ($externalId === '') {
            $externalId = hash('sha256', $url.'|'.$title);
        }

        $company = is_array($job['entreprise'] ?? null)
            ? $this->clean((string) ($job['entreprise']['nom'] ?? ''))
            : '';
        $location = is_array($job['lieuTravail'] ?? null)
            ? $this->clean((string) ($job['lieuTravail']['libelle'] ?? ''))
            : '';
        $contractCode = strtoupper(trim((string) ($job['typeContrat'] ?? '')));
        $contractLabel = $this->clean((string) ($job['typeContratLibelle'] ?? ''));
        $combined = implode(' ', [$title, $description, $contractLabel, (string) ($job['natureContrat'] ?? '')]);
        [$salaryMin, $salaryMax] = $this->annualSalary($job['salaire'] ?? null);

        return [
            'source' => $this->name(),
            'sourceUrl' => $url !== '' ? $url : null,
            'externalId' => $externalId,
            'title' => $title,
            'company' => $company,
            'location' => $location,
            'contractType' => $this->contractType($contractCode, $contractLabel, $combined),
            'workMode' => $this->workMode($combined),
            'description' => $description !== '' ? $description : $title,
            'publishedAt' => $this->date((string) ($job['dateCreation'] ?? $job['dateActualisation'] ?? '')),
            'salaryMin' => $salaryMin,
            'salaryMax' => $salaryMax,
            'rawData' => $job,
        ];
    }

    private function contractType(string $code, string $label, string $text): string
    {
        if ($code === 'CDI' || preg_match('/\bcdi\b/i', $label) === 1) {
            return 'CDI';
        }
        if ($code === 'CDD' || preg_match('/\bcdd\b/i', $label) === 1) {
            return 'CDD';
        }
        if (preg_match('/freelance|indépendant|independant|profession libérale|non salarié/i', $text) === 1) {
            return 'Freelance';
        }

        return $label !== '' ? $label : ($code !== '' ? $code : 'Autre');
    }

    private function workMode(string $text): string
    {
        if (preg_match('/télétravail total|teletravail total|100 ?% remote|full remote/i', $text) === 1) {
            return 'Télétravail';
        }
        if (preg_match('/télétravail|teletravail|hybride|hybrid|remote/i', $text) === 1) {
            return 'Hybride';
        }

        return 'Sur site';
    }

    /** @return array{0: ?int, 1: ?int} */
    private function annualSalary(mixed $salary): array
    {
        if (!is_array($salary)) {
            return [null, null];
        }

        $label = $this->clean((string) ($salary['libelle'] ?? ''));
        if ($label === '' || preg_match('/annuel|an\b|par an/i', $label) !== 1) {
            return [null, null];
        }

        if (preg_match('/(?:de\s+)?([0-9][0-9\s.,]*)\s*(?:euros?|€).*?(?:à|a|-)\s*([0-9][0-9\s.,]*)\s*(?:euros?|€)/iu', $label, $matches) === 1) {
            $first = $this->salaryNumber($matches[1]);
            $second = $this->salaryNumber($matches[2]);

            return $first !== null && $second !== null
                ? [min($first, $second), max($first, $second)]
                : [null, null];
        }

        if (preg_match('/([0-9][0-9\s.,]*)\s*(?:euros?|€)/iu', $label, $matches) === 1) {
            $value = $this->salaryNumber($matches[1]);

            return [$value, $value];
        }

        return [null, null];
    }

    private function salaryNumber(string $value): ?int
    {
        $value = str_replace(["\u{00A0}", ' '], '', trim($value));
        $value = str_replace(',', '.', $value);
        if (!is_numeric($value)) {
            return null;
        }

        return max(0, (int) round((float) $value));
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
