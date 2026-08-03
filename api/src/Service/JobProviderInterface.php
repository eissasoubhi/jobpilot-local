<?php

declare(strict_types=1);

namespace App\Service;

interface JobProviderInterface
{
    public function name(): string;

    public function isConfigured(): bool;

    /**
     * @param list<string> $targetJobs
     * @param list<string> $skills
     * @return list<array<string, mixed>>
     */
    public function search(array $targetJobs, array $skills): array;
}
