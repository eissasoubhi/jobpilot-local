<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;

final class MarketSkillsReportService
{
    public function __construct(private JobProfileTechnologyComparisonService $technologyComparison)
    {
    }

    /**
     * @param list<JobOffer> $jobs
     * @return array{
     *   analyzedJobs: int,
     *   configuredSkillsCount: int,
     *   demanded: list<array{label: string, count: int, coveragePercent: float}>,
     *   matching: list<array{label: string, count: int, coveragePercent: float}>,
     *   unconfigured: list<array{label: string, count: int, coveragePercent: float}>
     * }
     */
    public function summarize(array $jobs, UserSettings $settings, int $limit = 5): array
    {
        $demanded = [];
        $matching = [];
        $unconfigured = [];

        foreach ($jobs as $job) {
            $comparison = $this->technologyComparison->compare($job, $settings);
            $this->accumulate($demanded, $comparison['technologies']);
            $this->accumulate($matching, $comparison['matchingTechnologies']);
            $this->accumulate($unconfigured, $comparison['missingTechnologies']);
        }

        $jobCount = count($jobs);

        return [
            'analyzedJobs' => $jobCount,
            'configuredSkillsCount' => $this->configuredSkillsCount($settings),
            'demanded' => $this->rank($demanded, $jobCount, $limit),
            'matching' => $this->rank($matching, $jobCount, $limit),
            'unconfigured' => $this->rank($unconfigured, $jobCount, $limit),
        ];
    }

    /** @param array<string, array{label: string, count: int}> $bucket
     *  @param list<string> $values
     */
    private function accumulate(array &$bucket, array $values): void
    {
        $seen = [];
        foreach ($values as $value) {
            $label = trim($value);
            if ($label === '') {
                continue;
            }

            $key = mb_strtolower($label);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $bucket[$key] ??= ['label' => $label, 'count' => 0];
            ++$bucket[$key]['count'];
        }
    }

    /**
     * @param array<string, array{label: string, count: int}> $bucket
     * @return list<array{label: string, count: int, coveragePercent: float}>
     */
    private function rank(array $bucket, int $jobCount, int $limit): array
    {
        $rows = array_values($bucket);
        usort($rows, static function (array $left, array $right): int {
            $countComparison = $right['count'] <=> $left['count'];
            if ($countComparison !== 0) {
                return $countComparison;
            }

            return strcasecmp((string) $left['label'], (string) $right['label']);
        });

        return array_map(
            static fn (array $row): array => [
                'label' => $row['label'],
                'count' => $row['count'],
                'coveragePercent' => $jobCount > 0 ? round($row['count'] * 100 / $jobCount, 1) : 0.0,
            ],
            array_slice($rows, 0, max(1, $limit)),
        );
    }

    private function configuredSkillsCount(UserSettings $settings): int
    {
        $skills = [];
        foreach ($settings->getSkills() as $skill) {
            $skill = trim($skill);
            if ($skill !== '') {
                $skills[mb_strtolower($skill)] = true;
            }
        }

        return count($skills);
    }
}
