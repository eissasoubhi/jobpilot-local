<?php

declare(strict_types=1);

namespace App\Service\Ai;

interface DomOfferExtractorInterface
{
    /**
     * @return array{
     *   offers: list<array<string, mixed>>,
     *   confidence: float,
     *   notes: list<string>,
     *   model: string,
     *   cacheHit: bool
     * }
     */
    public function extract(
        string $sourceName,
        string $domain,
        string $pageUrl,
        string $dom,
    ): array;
}
