<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;

final class JobPriorityScoreService
{
    private const MATCH_WEIGHT = 0.60;
    private const FRESHNESS_WEIGHT = 0.15;
    private const PREFERENCE_WEIGHT = 0.10;
    private const COMPENSATION_WEIGHT = 0.07;
    private const CONFIDENCE_WEIGHT = 0.05;
    private const HISTORY_WEIGHT = 0.03;
    private const FRESHNESS_HALF_LIFE_HOURS = 72.0;
    private const HISTORY_PRIOR_SAMPLE_SIZE = 10;
    private const HISTORY_PRIOR_SCORE = 50.0;

    /**
     * @param array<string, array<string, int|string|float|null>> $sourcePerformance
     * @return array{
     *   score:int,
     *   reasons:list<string>,
     *   components:array{match:int,freshness:int,preferences:int,compensation:int,confidence:int,history:int}
     * }
     */
    public function evaluate(JobOffer $job, CandidateProfile $profile, array $sourcePerformance = []): array
    {
        if ($job->getStatus() === 'REJECTED_BY_FILTER') {
            return [
                'score' => 0,
                'reasons' => ['Offre exclue par un filtre bloquant : elle ne doit pas remonter dans la priorité.'],
                'components' => [
                    'match' => $job->getScore(),
                    'freshness' => $this->freshnessScore($job),
                    'preferences' => 0,
                    'compensation' => 0,
                    'confidence' => $this->confidenceScore($job),
                    'history' => 0,
                ],
            ];
        }

        $match = $job->getScore();
        $freshness = $this->freshnessScore($job);
        $preferences = $this->preferenceScore($job, $profile);
        $compensation = $this->compensationScore($job, $profile);
        $confidence = $this->confidenceScore($job);
        $history = $this->historyScore($job, $sourcePerformance);

        $score = (int) round(
            self::MATCH_WEIGHT * $match
            + self::FRESHNESS_WEIGHT * $freshness
            + self::PREFERENCE_WEIGHT * $preferences
            + self::COMPENSATION_WEIGHT * $compensation
            + self::CONFIDENCE_WEIGHT * $confidence
            + self::HISTORY_WEIGHT * $history,
        );
        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'reasons' => [
                sprintf('Correspondance profil : %d/100 · poids 60%%', $match),
                sprintf('Fraîcheur continue : %d/100 · poids 15%%', $freshness),
                sprintf('Préférences contrat / lieu / télétravail : %d/100 · poids 10%%', $preferences),
                sprintf('Rémunération par rapport à la cible : %d/100 · poids 7%%', $compensation),
                sprintf('Confiance et qualité des données : %d/100 · poids 5%%', $confidence),
                sprintf('Historique de conversion des sources : %d/100 · poids 3%%', $history),
            ],
            'components' => [
                'match' => $match,
                'freshness' => $freshness,
                'preferences' => $preferences,
                'compensation' => $compensation,
                'confidence' => $confidence,
                'history' => $history,
            ],
        ];
    }

    private function freshnessScore(JobOffer $job): int
    {
        $publishedAt = $job->getPublishedAt();
        if ($publishedAt === null) {
            return 50;
        }

        $ageHours = max(0.0, (time() - $publishedAt->getTimestamp()) / 3600);
        $score = 100.0 * pow(2.0, -$ageHours / self::FRESHNESS_HALF_LIFE_HOURS);

        return (int) round(max(0.0, min(100.0, $score)));
    }

    private function preferenceScore(JobOffer $job, CandidateProfile $profile): int
    {
        $data = $profile->toArray();
        $scores = [];

        $acceptedContracts = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            is_array($data['acceptedContracts'] ?? null) ? $data['acceptedContracts'] : [],
        )));
        if ($acceptedContracts !== []) {
            $contract = $this->normalize((string) $job->getContractType());
            if ($contract === '') {
                $scores[] = 50;
            } else {
                $matches = array_filter($acceptedContracts, fn (string $accepted): bool => $this->termsMatch($contract, $accepted));
                $scores[] = $matches !== [] ? 100 : 25;
            }
        }

        $workMode = $this->normalize($job->getWorkMode());
        $preferredLocations = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            is_array($data['preferredLocations'] ?? null) ? $data['preferredLocations'] : [],
        )));
        if ($preferredLocations !== [] && !$this->isFullyRemote($workMode)) {
            // For a genuinely full-remote offer, the posting city is informational rather than
            // a job constraint. Excluding the location dimension is neutral: it neither rewards
            // nor penalizes the offer because a source happens to report an HQ or admin city.
            $location = $this->normalize($job->getLocation());
            if ($location === '') {
                $scores[] = 50;
            } else {
                $matches = array_filter($preferredLocations, fn (string $preferred): bool => $this->locationMatches($location, $preferred));
                $scores[] = $matches !== [] ? 100 : 35;
            }
        }

        $workModePreference = trim((string) ($data['workModePreference'] ?? ''));
        if ($this->isMeaningfulPreference($workModePreference)) {
            $preference = $this->normalize($workModePreference);
            if ($workMode === '') {
                $scores[] = 50;
            } elseif ($this->workModesMatch($workMode, $preference)) {
                $scores[] = 100;
            } else {
                $scores[] = 35;
            }
        }

        if ($scores === []) {
            return 50;
        }

        return (int) round(array_sum($scores) / count($scores));
    }

    private function compensationScore(JobOffer $job, CandidateProfile $profile): int
    {
        $data = $profile->toArray();
        $contract = $this->normalize($job->getContractType());
        $isFreelance = preg_match('/freelance|mission|portage|sous.?traitance/', $contract) === 1;

        if ($isFreelance) {
            $target = isset($data['desiredTjm']) && is_numeric($data['desiredTjm']) ? (int) $data['desiredTjm'] : 0;
            if ($target <= 0) {
                return 50;
            }

            $value = $this->representativeCompensation(
                $job->getProposedTjm() ?? $job->getTjmFixed(),
                $job->getTjmMin(),
                $job->getTjmMax(),
            );

            return $this->ratioScore($value, $target);
        }

        $target = isset($data['desiredSalary']) && is_numeric($data['desiredSalary']) ? (int) $data['desiredSalary'] : 0;
        if ($target <= 0) {
            return 50;
        }

        $value = $this->representativeCompensation(
            $job->getProposedSalary(),
            $job->getSalaryMin(),
            $job->getSalaryMax(),
        );

        return $this->ratioScore($value, $target);
    }

    private function representativeCompensation(?int $proposed, ?int $minimum, ?int $maximum): ?int
    {
        if ($proposed !== null && $proposed > 0) {
            return $proposed;
        }

        $hasMinimum = $minimum !== null && $minimum > 0;
        $hasMaximum = $maximum !== null && $maximum > 0;
        if ($hasMinimum && $hasMaximum) {
            return (int) round(($minimum + $maximum) / 2);
        }

        if ($hasMinimum) {
            return $minimum;
        }

        return $hasMaximum ? $maximum : null;
    }

    private function ratioScore(?int $value, int $target): int
    {
        if ($value === null || $value <= 0 || $target <= 0) {
            return 50;
        }

        $ratio = $value / $target;

        return match (true) {
            $ratio >= 1.0 => 100,
            $ratio >= 0.95 => 90,
            $ratio >= 0.90 => 80,
            $ratio >= 0.80 => 60,
            $ratio >= 0.70 => 40,
            default => 20,
        };
    }

    private function confidenceScore(JobOffer $job): int
    {
        $payload = $job->toArray();
        $reasons = is_array($payload['scoreReasons'] ?? null) ? $payload['scoreReasons'] : [];
        $aiConfidence = null;
        foreach ($reasons as $reason) {
            if (!is_string($reason)) {
                continue;
            }
            if (preg_match('/confiance\s+(\d{1,3})%/iu', $reason, $matches) === 1) {
                $aiConfidence = max(0, min(100, (int) $matches[1]));
                break;
            }
        }

        $quality = 0;
        $quality += trim($job->getTitle()) !== '' ? 20 : 0;
        $descriptionLength = mb_strlen(trim($job->getDescription()));
        $quality += min(40, (int) round(($descriptionLength / 300) * 40));
        $quality += trim($job->getContractType()) !== '' ? 10 : 0;
        $quality += trim($job->getLocation()) !== '' ? 10 : 0;
        $quality += $job->getPublishedAt() !== null ? 10 : 0;
        $quality += trim($job->getSource()) !== '' ? 10 : 0;
        $quality = max(0, min(100, $quality));

        if ($aiConfidence === null) {
            return $quality;
        }

        return (int) round($aiConfidence * 0.7 + $quality * 0.3);
    }

    /** @param array<string, array<string, int|string|float|null>> $sourcePerformance */
    private function historyScore(JobOffer $job, array $sourcePerformance): int
    {
        if ($sourcePerformance === []) {
            return 50;
        }

        $totalSubmitted = 0;
        $weightedObserved = 0.0;
        $sources = $job->toArray()['sources'] ?? [];
        foreach (is_array($sources) ? $sources : [] as $source) {
            if (!is_array($source)) {
                continue;
            }
            $code = strtolower(trim((string) ($source['sourceCode'] ?? '')));
            if ($code === '' || !isset($sourcePerformance[$code])) {
                continue;
            }

            $row = $sourcePerformance[$code];
            $submitted = max(0, (int) ($row['submitted'] ?? 0));
            if ($submitted === 0) {
                continue;
            }

            $responseRate = max(0.0, min(100.0, (float) ($row['responseRate'] ?? 0.0)));
            $interviewRate = max(0.0, min(100.0, (float) ($row['interviewRate'] ?? 0.0)));
            $observed = 0.35 * $responseRate + 0.65 * $interviewRate;
            $totalSubmitted += $submitted;
            $weightedObserved += $submitted * $observed;
        }

        if ($totalSubmitted === 0) {
            return 50;
        }

        return (int) round(
            (self::HISTORY_PRIOR_SAMPLE_SIZE * self::HISTORY_PRIOR_SCORE + $weightedObserved)
            / (self::HISTORY_PRIOR_SAMPLE_SIZE + $totalSubmitted),
        );
    }

    private function termsMatch(string $normalizedJobValue, string $candidateValue): bool
    {
        $normalizedCandidate = $this->normalize($candidateValue);
        if ($normalizedCandidate === '') {
            return false;
        }

        return str_contains($normalizedJobValue, $normalizedCandidate)
            || str_contains($normalizedCandidate, $normalizedJobValue);
    }

    private function locationMatches(string $normalizedLocation, string $candidateValue): bool
    {
        if ($this->termsMatch($normalizedLocation, $candidateValue)) {
            return true;
        }

        $preferred = $this->normalize($candidateValue);
        if (!$this->isIleDeFrancePreference($preferred)) {
            return false;
        }

        return $this->isIleDeFranceLocation($normalizedLocation);
    }

    private function isIleDeFrancePreference(string $value): bool
    {
        return $value === 'idf'
            || str_contains($value, 'ile-de-france')
            || str_contains($value, 'ile de france')
            || str_contains($value, 'region parisienne');
    }

    private function isIleDeFranceLocation(string $location): bool
    {
        if (preg_match('/\b(75|77|78|91|92|93|94|95)\d{3}\b/', $location) === 1) {
            return true;
        }

        foreach ([
            'paris', 'ile-de-france', 'ile de france', 'region parisienne',
            'hauts-de-seine', 'hauts de seine', 'seine-saint-denis', 'seine saint denis',
            'val-de-marne', 'val de marne', 'val-d-oise', 'val d oise', 'val-doise',
            'yvelines', 'essonne', 'seine-et-marne', 'seine et marne',
        ] as $token) {
            if (str_contains($location, $token)) {
                return true;
            }
        }

        return false;
    }

    private function workModesMatch(string $workMode, string $preference): bool
    {
        $families = [
            ['remote', 'full remote', 'teletravail', 'distance'],
            ['hybride', 'hybrid'],
            ['presentiel', 'onsite', 'sur site'],
        ];

        foreach ($families as $family) {
            $jobMatches = array_filter($family, static fn (string $token): bool => str_contains($workMode, $token));
            $preferenceMatches = array_filter($family, static fn (string $token): bool => str_contains($preference, $token));
            if ($jobMatches !== [] && $preferenceMatches !== []) {
                return true;
            }
        }

        return str_contains($workMode, $preference) || str_contains($preference, $workMode);
    }

    private function isFullyRemote(string $workMode): bool
    {
        if ($workMode === '') {
            return false;
        }

        return str_contains($workMode, 'full remote')
            || str_contains($workMode, '100% remote')
            || str_contains($workMode, '100 % remote')
            || str_contains($workMode, 'remote complet')
            || str_contains($workMode, 'teletravail complet')
            || $workMode === 'remote';
    }

    private function isMeaningfulPreference(string $value): bool
    {
        $normalized = $this->normalize($value);
        if ($normalized === '') {
            return false;
        }

        foreach (['aucune preference', 'sans preference', 'indifferent', 'indifferente', 'any'] as $neutral) {
            if (str_contains($normalized, $neutral)) {
                return false;
            }
        }

        return true;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a',
            'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        ]);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
