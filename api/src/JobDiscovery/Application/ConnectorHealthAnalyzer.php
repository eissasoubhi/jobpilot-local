<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

final class ConnectorHealthAnalyzer
{
    /**
     * @param list<array<string, mixed>> $runs Newest run first.
     * @return array{
     *   status: string,
     *   label: string,
     *   alert: bool,
     *   sampleSize: int,
     *   consecutiveZeroRuns: int,
     *   lastExtractionRate: float|null,
     *   baselineAverageReceived: float|null,
     *   reasons: list<string>
     * }
     */
    public function analyze(array $runs): array
    {
        $completed = array_values(array_filter(
            $runs,
            static fn (array $run): bool => strtoupper((string) ($run['status'] ?? '')) !== 'RUNNING',
        ));

        if ($completed === []) {
            return $this->result('NO_DATA', 0, 0, null, null, [
                'Aucune synchronisation terminée ne permet encore d’établir une référence.',
            ]);
        }

        $latest = $completed[0];
        $latestReceived = max(0, (int) ($latest['received'] ?? 0));
        $latestFailed = max(0, (int) ($latest['failed'] ?? 0));
        $latestStatus = strtoupper((string) ($latest['status'] ?? ''));
        $latestError = trim((string) ($latest['error'] ?? ''));
        $lastExtractionRate = $latestReceived > 0
            ? round(max(0, $latestReceived - $latestFailed) * 100 / $latestReceived, 1)
            : null;
        $latestDetails = is_array($latest['details'] ?? null) ? $latest['details'] : [];
        $fieldQuality = is_array($latestDetails['fieldQuality'] ?? null) ? $latestDetails['fieldQuality'] : [];
        $requiredCompleteness = is_numeric($fieldQuality['requiredCompleteness'] ?? null)
            ? (float) $fieldQuality['requiredCompleteness']
            : null;
        $recommendedCompleteness = is_numeric($fieldQuality['recommendedCompleteness'] ?? null)
            ? (float) $fieldQuality['recommendedCompleteness']
            : null;

        $consecutiveZeroRuns = 0;
        foreach ($completed as $run) {
            if (max(0, (int) ($run['received'] ?? 0)) > 0) {
                break;
            }
            ++$consecutiveZeroRuns;
        }

        $positiveRuns = array_values(array_filter(
            $completed,
            static fn (array $run): bool => max(0, (int) ($run['received'] ?? 0)) > 0,
        ));
        $baselineAverageReceived = $positiveRuns === []
            ? null
            : round(array_sum(array_map(
                static fn (array $run): int => max(0, (int) ($run['received'] ?? 0)),
                $positiveRuns,
            )) / count($positiveRuns), 1);

        if ($latestStatus === 'FAILED' || $latestError !== '') {
            return $this->result('BROKEN', count($completed), $consecutiveZeroRuns, $lastExtractionRate, $baselineAverageReceived, [
                $latestError !== ''
                    ? sprintf('La dernière synchronisation a échoué : %s', $latestError)
                    : 'La dernière synchronisation est marquée comme échouée.',
            ]);
        }

        if ($latestReceived > 0 && $requiredCompleteness !== null && $requiredCompleteness < 80.0) {
            return $this->result('BROKEN', count($completed), $consecutiveZeroRuns, $lastExtractionRate, $baselineAverageReceived, [
                sprintf('La complétude des champs obligatoires est tombée à %.1f %%.', $requiredCompleteness),
            ]);
        }

        if ($latestReceived > 0 && $lastExtractionRate !== null && $lastExtractionRate < 50.0) {
            return $this->result('BROKEN', count($completed), $consecutiveZeroRuns, $lastExtractionRate, $baselineAverageReceived, [
                sprintf('Seulement %.1f %% des enregistrements reçus ont été normalisés sans échec.', $lastExtractionRate),
            ]);
        }

        if ($consecutiveZeroRuns >= 3 && $positiveRuns !== []) {
            return $this->result('BROKEN', count($completed), $consecutiveZeroRuns, $lastExtractionRate, $baselineAverageReceived, [
                sprintf('%d synchronisations consécutives ne retournent plus aucune offre alors qu’une référence positive existe.', $consecutiveZeroRuns),
            ]);
        }

        if ($latestReceived > 0 && $requiredCompleteness !== null && $requiredCompleteness < 100.0) {
            return $this->result('DEGRADED', count($completed), $consecutiveZeroRuns, $lastExtractionRate, $baselineAverageReceived, [
                sprintf('Des offres ont des champs obligatoires manquants : complétude %.1f %%.', $requiredCompleteness),
            ]);
        }

        if ($latestReceived > 0 && $lastExtractionRate !== null && $lastExtractionRate < 90.0) {
            return $this->result('DEGRADED', count($completed), $consecutiveZeroRuns, $lastExtractionRate, $baselineAverageReceived, [
                sprintf('Le taux de normalisation de la dernière synchronisation est tombé à %.1f %%.', $lastExtractionRate),
            ]);
        }

        if ($consecutiveZeroRuns >= 2 && $positiveRuns !== []) {
            return $this->result('DEGRADED', count($completed), $consecutiveZeroRuns, $lastExtractionRate, $baselineAverageReceived, [
                sprintf('%d synchronisations consécutives ne retournent aucune offre.', $consecutiveZeroRuns),
            ]);
        }

        if ($latestReceived > 0 && $recommendedCompleteness !== null && $recommendedCompleteness < 50.0) {
            return $this->result('WATCH', count($completed), $consecutiveZeroRuns, $lastExtractionRate, $baselineAverageReceived, [
                sprintf('La source reste exploitable, mais seulement %.1f %% des champs recommandés sont renseignés.', $recommendedCompleteness),
            ]);
        }

        if ($consecutiveZeroRuns === 1 && $positiveRuns !== []) {
            return $this->result('WATCH', count($completed), $consecutiveZeroRuns, $lastExtractionRate, $baselineAverageReceived, [
                'La dernière synchronisation ne retourne aucune offre ; une confirmation est attendue au prochain passage.',
            ]);
        }

        if ($positiveRuns === []) {
            return $this->result('NO_DATA', count($completed), $consecutiveZeroRuns, $lastExtractionRate, null, [
                'Le connecteur n’a encore produit aucune offre ; aucune rupture ne peut être confirmée sans référence positive.',
            ]);
        }

        return $this->result('HEALTHY', count($completed), $consecutiveZeroRuns, $lastExtractionRate, $baselineAverageReceived, [
            'Les dernières synchronisations restent cohérentes avec la référence observée.',
        ]);
    }

    /**
     * @param list<string> $reasons
     * @return array{
     *   status: string,
     *   label: string,
     *   alert: bool,
     *   sampleSize: int,
     *   consecutiveZeroRuns: int,
     *   lastExtractionRate: float|null,
     *   baselineAverageReceived: float|null,
     *   reasons: list<string>
     * }
     */
    private function result(
        string $status,
        int $sampleSize,
        int $consecutiveZeroRuns,
        ?float $lastExtractionRate,
        ?float $baselineAverageReceived,
        array $reasons,
    ): array {
        return [
            'status' => $status,
            'label' => match ($status) {
                'HEALTHY' => 'Extraction saine',
                'WATCH' => 'À surveiller',
                'DEGRADED' => 'Extraction dégradée',
                'BROKEN' => 'Rupture probable',
                default => 'Référence insuffisante',
            },
            'alert' => in_array($status, ['DEGRADED', 'BROKEN'], true),
            'sampleSize' => $sampleSize,
            'consecutiveZeroRuns' => $consecutiveZeroRuns,
            'lastExtractionRate' => $lastExtractionRate,
            'baselineAverageReceived' => $baselineAverageReceived,
            'reasons' => $reasons,
        ];
    }
}
