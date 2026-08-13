<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JobOffer;
use App\Entity\PreferenceSignal;
use App\Entity\UserSettings;

final class PreferenceFeatureExtractionService
{
    public function __construct(private JobProfileTechnologyComparisonService $technologyComparison)
    {
    }

    /**
     * @return array{
     *   signalType: string,
     *   origin: string,
     *   preferenceValue: int,
     *   dimensions: array<string, list<string>>,
     *   numeric: array{salaryAnnual: ?int, tjm: ?int}
     * }
     */
    public function extract(PreferenceSignal $signal, UserSettings $settings): array
    {
        $job = $signal->getJobOffer();
        $comparison = $this->technologyComparison->compare($job, $settings);

        return [
            'signalType' => $signal->getSignalType(),
            'origin' => $signal->getOrigin(),
            'preferenceValue' => $signal->getPreferenceValue(),
            'dimensions' => [
                'title' => $this->single($this->normalizeLabel($job->getTitle())),
                'technologies' => $this->normalizeList($comparison['technologies']),
                'location' => $this->single($this->normalizeLabel($job->getLocation())),
                'contractType' => $this->single($this->normalizeLabel($job->getContractType())),
                'workMode' => $this->single($this->normalizeLabel($job->getWorkMode())),
                'company' => $this->single($this->normalizeLabel($job->getCompany())),
            ],
            'numeric' => [
                'salaryAnnual' => $this->midpoint($job->getSalaryMin(), $job->getSalaryMax()),
                'tjm' => $this->tjm($job),
            ],
        ];
    }

    /**
     * @param list<PreferenceSignal> $signals
     * @return array<string, list<array{value: string, positive: int, negative: int, outcomes: int, total: int}>>
     */
    public function summarize(array $signals, UserSettings $settings, int $limitPerDimension = 10): array
    {
        $buckets = [];

        foreach ($signals as $signal) {
            $extracted = $this->extract($signal, $settings);
            if ($extracted['preferenceValue'] === 0) {
                continue;
            }

            foreach ($extracted['dimensions'] as $dimension => $values) {
                foreach (array_unique($values) as $value) {
                    $key = mb_strtolower($value);
                    $buckets[$dimension][$key] ??= [
                        'value' => $value,
                        'positive' => 0,
                        'negative' => 0,
                        'outcomes' => 0,
                        'total' => 0,
                    ];

                    if ($extracted['preferenceValue'] > 0) {
                        ++$buckets[$dimension][$key]['positive'];
                    } else {
                        ++$buckets[$dimension][$key]['negative'];
                    }
                    if ($signal->getOrigin() === PreferenceSignal::ORIGIN_PIPELINE_OUTCOME) {
                        ++$buckets[$dimension][$key]['outcomes'];
                    }
                    ++$buckets[$dimension][$key]['total'];
                }
            }
        }

        $result = [];
        foreach ($buckets as $dimension => $values) {
            $rows = array_values($values);
            usort($rows, static function (array $left, array $right): int {
                $total = $right['total'] <=> $left['total'];
                if ($total !== 0) return $total;
                $outcomes = $right['outcomes'] <=> $left['outcomes'];
                if ($outcomes !== 0) return $outcomes;

                return strcasecmp($left['value'], $right['value']);
            });
            $result[$dimension] = array_slice($rows, 0, max(1, $limitPerDimension));
        }

        return $result;
    }

    /** @return list<string> */
    private function single(string $value): array
    {
        return $value === '' ? [] : [$value];
    }

    /** @param list<string> $values
     *  @return list<string>
     */
    private function normalizeList(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            $value = trim($value);
            if ($value === '') continue;
            $result[mb_strtolower($value)] = $value;
        }

        return array_values($result);
    }

    private function normalizeLabel(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }

    private function midpoint(?int $minimum, ?int $maximum): ?int
    {
        if ($minimum === null && $maximum === null) return null;
        if ($minimum === null) return $maximum;
        if ($maximum === null) return $minimum;

        return (int) round(($minimum + $maximum) / 2);
    }

    private function tjm(JobOffer $job): ?int
    {
        if ($job->getProposedTjm() !== null) return $job->getProposedTjm();
        if ($job->getTjmFixed() !== null) return $job->getTjmFixed();

        return $this->midpoint($job->getTjmMin(), $job->getTjmMax());
    }
}
