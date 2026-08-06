<?php

declare(strict_types=1);

namespace App\JobDiscovery\Domain\Connector;

interface SearchCriteriaPreviewConnector
{
    /**
     * @param list<string> $targetJobs
     * @param list<string> $skills
     *
     * @return list<string>
     */
    public function previewSearchQueries(array $targetJobs, array $skills): array;
}
