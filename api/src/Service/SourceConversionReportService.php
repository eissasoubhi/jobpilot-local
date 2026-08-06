<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;

final class SourceConversionReportService
{
    private const STRONG_MATCH_THRESHOLD = 60;

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return array{sources: list<array<string, int|string|float|null>>, contractTypes: list<array<string, int|string|float|null>>, workModes: list<array<string, int|string|float|null>>, totals: array<string, int>} */
    public function report(): array
    {
        $sourceRows = [];
        $contractTypeRows = [];
        $workModeRows = [];
        $jobs = $this->em->getRepository(JobOffer::class)->findAll();
        $applications = $this->em->getRepository(Application::class)->findAll();
        $applicationsByJob = [];

        foreach ($applications as $application) {
            $jobId = $application->getJobOffer()->getId();
            if ($jobId !== null) {
                $applicationsByJob[$jobId][] = $application;
            }
        }

        foreach ($jobs as $job) {
            $jobApplications = $applicationsByJob[$job->getId() ?? 0] ?? [];
            $sources = $job->toArray()['sources'] ?? [];
            $seenCodes = [];

            foreach (is_array($sources) ? $sources : [] as $source) {
                if (!is_array($source)) {
                    continue;
                }

                $code = strtolower(trim((string) ($source['sourceCode'] ?? 'manual')));
                if ($code === '' || isset($seenCodes[$code])) {
                    continue;
                }
                $seenCodes[$code] = true;
                $name = trim((string) ($source['sourceName'] ?? $code));
                $this->accumulate($sourceRows, $code, $name !== '' ? $name : $code, $job, $jobApplications);
            }

            $this->accumulateByStoredValue($contractTypeRows, $job->getContractType(), $job, $jobApplications);
            $this->accumulateByStoredValue($workModeRows, $job->getWorkMode(), $job, $jobApplications);
        }

        $sources = $this->finalizeRows($sourceRows);
        $contractTypes = $this->finalizeRows($contractTypeRows);
        $workModes = $this->finalizeRows($workModeRows);

        return [
            'sources' => $sources,
            'contractTypes' => $contractTypes,
            'workModes' => $workModes,
            'totals' => [
                'offers' => count($jobs),
                'applications' => count($applications),
                'sources' => count($sources),
                'contractTypes' => count($contractTypes),
                'workModes' => count($workModes),
            ],
        ];
    }

    /** @param array<string, array<string, int|string>> $rows
     *  @param list<Application> $applications
     */
    private function accumulateByStoredValue(array &$rows, string $value, JobOffer $job, array $applications): void
    {
        $trimmedValue = trim($value);
        $this->accumulate(
            $rows,
            $trimmedValue === '' ? 'unknown' : mb_strtolower($trimmedValue),
            $trimmedValue === '' ? 'Non renseigné' : $trimmedValue,
            $job,
            $applications,
        );
    }

    /** @param array<string, array<string, int|string>> $rows
     *  @param list<Application> $applications
     */
    private function accumulate(array &$rows, string $code, string $name, JobOffer $job, array $applications): void
    {
        $rows[$code] ??= [
            'code' => $code,
            'name' => $name,
            'offers' => 0,
            'applications' => 0,
            'submitted' => 0,
            'responses' => 0,
            'interviews' => 0,
            'rejections' => 0,
            'tjmProposalCount' => 0,
            'salaryProposalCount' => 0,
            'proposedTjmTotal' => 0,
            'proposedSalaryTotal' => 0,
            'matchingScoreTotal' => 0,
            'strongMatches' => 0,
        ];
        ++$rows[$code]['offers'];
        $rows[$code]['matchingScoreTotal'] += $job->getScore();
        if ($job->getScore() >= self::STRONG_MATCH_THRESHOLD) {
            ++$rows[$code]['strongMatches'];
        }

        $proposedTjm = $job->getProposedTjm();
        if ($proposedTjm !== null) {
            ++$rows[$code]['tjmProposalCount'];
            $rows[$code]['proposedTjmTotal'] += $proposedTjm;
        }

        $proposedSalary = $job->getProposedSalary();
        if ($proposedSalary !== null) {
            ++$rows[$code]['salaryProposalCount'];
            $rows[$code]['proposedSalaryTotal'] += $proposedSalary;
        }

        foreach ($applications as $application) {
            ++$rows[$code]['applications'];
            $status = $application->getStatus();
            if ($application->getSubmittedAt() !== null || in_array($status, [
                'SUBMITTED', 'APPLICATION_CONFIRMED', 'RESPONSE_RECEIVED', 'INFORMATION_REQUESTED', 'INTERVIEW', 'REJECTED',
            ], true)) {
                ++$rows[$code]['submitted'];
            }
            if (in_array($status, ['RESPONSE_RECEIVED', 'INFORMATION_REQUESTED', 'INTERVIEW', 'REJECTED'], true)) {
                ++$rows[$code]['responses'];
            }
            if ($status === 'INTERVIEW') {
                ++$rows[$code]['interviews'];
            }
            if ($status === 'REJECTED') {
                ++$rows[$code]['rejections'];
            }
        }
    }

    /** @param array<string, array<string, int|string>> $rows
     *  @return list<array<string, int|string|float|null>>
     */
    private function finalizeRows(array $rows): array
    {
        $finalRows = array_values(array_map(static function (array $row): array {
            $row['applicationRate'] = $row['offers'] > 0 ? round($row['applications'] * 100 / $row['offers'], 1) : 0.0;
            $row['responseRate'] = $row['submitted'] > 0 ? round($row['responses'] * 100 / $row['submitted'], 1) : 0.0;
            $row['interviewRate'] = $row['submitted'] > 0 ? round($row['interviews'] * 100 / $row['submitted'], 1) : 0.0;
            $row['averageMatchingScore'] = $row['offers'] > 0
                ? round($row['matchingScoreTotal'] / $row['offers'], 1)
                : 0.0;
            $row['strongMatchRate'] = $row['offers'] > 0
                ? round($row['strongMatches'] * 100 / $row['offers'], 1)
                : 0.0;
            $row['averageProposedTjm'] = $row['tjmProposalCount'] > 0
                ? (int) round($row['proposedTjmTotal'] / $row['tjmProposalCount'])
                : null;
            $row['averageProposedSalary'] = $row['salaryProposalCount'] > 0
                ? (int) round($row['proposedSalaryTotal'] / $row['salaryProposalCount'])
                : null;
            unset($row['matchingScoreTotal'], $row['proposedTjmTotal'], $row['proposedSalaryTotal']);

            return $row;
        }, $rows));

        usort($finalRows, static fn (array $a, array $b): int => [$b['applications'], $b['offers'], $a['name']] <=> [$a['applications'], $a['offers'], $b['name']]);

        return $finalRows;
    }
}
