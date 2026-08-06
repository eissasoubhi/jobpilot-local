<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;

final class SourceConversionReportService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return array{sources: list<array<string, int|string|float>>, totals: array<string, int>} */
    public function report(): array
    {
        $rows = [];
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
                $rows[$code] ??= [
                    'code' => $code,
                    'name' => $name !== '' ? $name : $code,
                    'offers' => 0,
                    'applications' => 0,
                    'submitted' => 0,
                    'responses' => 0,
                    'interviews' => 0,
                    'rejections' => 0,
                ];
                ++$rows[$code]['offers'];

                foreach ($applicationsByJob[$job->getId() ?? 0] ?? [] as $application) {
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
        }

        $sources = array_values(array_map(static function (array $row): array {
            $row['applicationRate'] = $row['offers'] > 0 ? round($row['applications'] * 100 / $row['offers'], 1) : 0.0;
            $row['responseRate'] = $row['submitted'] > 0 ? round($row['responses'] * 100 / $row['submitted'], 1) : 0.0;
            $row['interviewRate'] = $row['submitted'] > 0 ? round($row['interviews'] * 100 / $row['submitted'], 1) : 0.0;

            return $row;
        }, $rows));

        usort($sources, static fn (array $a, array $b): int => [$b['applications'], $b['offers'], $a['name']] <=> [$a['applications'], $a['offers'], $b['name']]);

        return [
            'sources' => $sources,
            'totals' => [
                'offers' => count($jobs),
                'applications' => count($applications),
                'sources' => count($sources),
            ],
        ];
    }
}
