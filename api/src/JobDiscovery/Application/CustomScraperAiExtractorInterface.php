<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

interface CustomScraperAiExtractorInterface
{
    /** @return list<array<string, mixed>> */
    public function extract(string $html, string $pageUrl, string $sourceName): array;
}
