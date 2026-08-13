<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JobOffer;
use App\Entity\PreferenceSignal;
use App\Entity\UserSettings;

final class PreferenceAdaptiveRankingService
{
    private const MAX_ADJUSTMENT = 12;
    private const MIN_SIGNALS = 3;

    /** @var array<string, float> */
    private const DIMENSION_WEIGHTS = [
        'technologies' => 3.0,
        'title' => 2.5,
        'workMode' => 2.0,
        'contractType' => 1.5,
        'location' => 1.5,
        'company' => 0.5,
    ];

    public function __construct(private PreferenceFeatureExtractionService $features)
    {
    }

    /**
     * @param list<PreferenceSignal> $signals
     * @param array{score: int, reasons: list<string>, hardRejected: bool} $compatibility
     * @return array{
     *   score: int,
     *   compatibilityScore: int,
     *   preferenceAdjustment: int,
     *   preferenceEvidence: int,
     *   preferenceReasons: list<string>,
     *   reasons: list<string>,
     *   hardRejected: bool
     * }
     */
    public function rank(JobOffer $job, UserSettings $settings, array $signals, array $compatibility): array
    {
        $baseScore = max(0, min(100, (int) $compatibility['score']));
        $reasons = $compatibility['reasons'];

        if ($compatibility['hardRejected']) {
            return $this->result($baseScore, 0, 0, [], $reasons, true);
        }

        $preferenceSignals = array_values(array_filter(
            $signals,
            static fn (PreferenceSignal $signal): bool => $signal->getPreferenceValue() !== 0,
        ));
        $signalCount = count($preferenceSignals);
        if ($signalCount < self::MIN_SIGNALS) {
            return $this->result($baseScore, 0, $signalCount, [], $reasons, false);
        }

        $jobFeatures = $this->features->extractJob($job, $settings);
        $summary = $this->features->summarize($preferenceSignals, $settings, 100);
        $weightedScore = 0.0;
        $usedWeight = 0.0;
        $preferenceReasons = [];

        foreach (self::DIMENSION_WEIGHTS as $dimension => $weight) {
            $values = $jobFeatures['dimensions'][$dimension] ?? [];
            if ($values === [] || !isset($summary[$dimension])) {
                continue;
            }

            $rows = [];
            foreach ($summary[$dimension] as $row) {
                $rows[mb_strtolower($row['value'])] = $row;
            }

            $ratios = [];
            $dimensionEvidence = 0;
            foreach ($values as $value) {
                $row = $rows[mb_strtolower($value)] ?? null;
                if ($row === null) {
                    continue;
                }

                $userPositive = max(0, $row['positive'] - $row['outcomes']);
                $weightedOutcome = $row['outcomes'] * 0.5;
                $denominator = $userPositive + $row['negative'] + $weightedOutcome;
                if ($denominator <= 0) {
                    continue;
                }

                $ratios[] = ($userPositive - $row['negative'] + $weightedOutcome) / $denominator;
                $dimensionEvidence += $row['total'];
            }

            if ($ratios === []) {
                continue;
            }

            $ratio = array_sum($ratios) / count($ratios);
            // Sparse dimensions should influence the ordering progressively rather than jump to the full weight.
            $confidence = min(1.0, $dimensionEvidence / 3);
            $weightedScore += $ratio * $confidence * $weight;
            $usedWeight += $weight;

            $direction = $ratio >= 0.15 ? 'favorise' : ($ratio <= -0.15 ? 'défavorise' : 'neutre');
            if ($direction !== 'neutre') {
                $preferenceReasons[] = sprintf(
                    '%s %s (%d signal%s)',
                    $this->dimensionLabel($dimension),
                    $direction,
                    $dimensionEvidence,
                    $dimensionEvidence > 1 ? 's' : '',
                );
            }
        }

        if ($usedWeight === 0.0) {
            return $this->result($baseScore, 0, $signalCount, [], $reasons, false);
        }

        $normalized = max(-1.0, min(1.0, $weightedScore / $usedWeight));
        $adjustment = (int) round($normalized * self::MAX_ADJUSTMENT);
        $adjustment = max(-self::MAX_ADJUSTMENT, min(self::MAX_ADJUSTMENT, $adjustment));
        $finalScore = max(0, min(100, $baseScore + $adjustment));

        if ($adjustment !== 0) {
            $reasons[] = sprintf(
                'Préférences apprises : %s%d point%s (ajustement borné à ±%d)',
                $adjustment > 0 ? '+' : '',
                $adjustment,
                abs($adjustment) > 1 ? 's' : '',
                self::MAX_ADJUSTMENT,
            );
        }

        return $this->result(
            $baseScore,
            $adjustment,
            $signalCount,
            array_slice($preferenceReasons, 0, 4),
            $reasons,
            false,
            $finalScore,
        );
    }

    /**
     * @param list<string> $preferenceReasons
     * @param list<string> $reasons
     * @return array{
     *   score: int,
     *   compatibilityScore: int,
     *   preferenceAdjustment: int,
     *   preferenceEvidence: int,
     *   preferenceReasons: list<string>,
     *   reasons: list<string>,
     *   hardRejected: bool
     * }
     */
    private function result(
        int $compatibilityScore,
        int $adjustment,
        int $evidence,
        array $preferenceReasons,
        array $reasons,
        bool $hardRejected,
        ?int $finalScore = null,
    ): array {
        return [
            'score' => $finalScore ?? $compatibilityScore,
            'compatibilityScore' => $compatibilityScore,
            'preferenceAdjustment' => $adjustment,
            'preferenceEvidence' => $evidence,
            'preferenceReasons' => $preferenceReasons,
            'reasons' => $reasons,
            'hardRejected' => $hardRejected,
        ];
    }

    private function dimensionLabel(string $dimension): string
    {
        return match ($dimension) {
            'technologies' => 'Technologies',
            'title' => 'Intitulé',
            'workMode' => 'Mode de travail',
            'contractType' => 'Contrat',
            'location' => 'Localisation',
            'company' => 'Entreprise',
            default => $dimension,
        };
    }
}
