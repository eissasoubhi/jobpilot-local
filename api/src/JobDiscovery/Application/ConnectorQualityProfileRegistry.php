<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

final class ConnectorQualityProfileRegistry
{
    /** @var array<string, array{required: list<string>, recommended: list<string>}> */
    private const PROFILES = [
        'symfony jobs' => [
            'required' => ['externalId', 'title', 'description', 'sourceUrl'],
            'recommended' => ['company', 'location', 'contractType', 'publishedAt'],
        ],
    ];

    /**
     * @return array{required: list<string>, recommended: list<string>}|null
     */
    public function forSource(?string $source): ?array
    {
        $normalized = mb_strtolower(trim((string) $source));

        return self::PROFILES[$normalized] ?? null;
    }
}
