<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Http;

final readonly class HttpScrapingResult
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        public string $url,
        public int $statusCode,
        public string $body,
        public array $headers,
        public int $attempts,
        public bool $fromCache = false,
    ) {
    }
}
