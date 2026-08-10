<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

interface BrowserRenderClientInterface
{
    public function isConfigured(): bool;

    /**
     * @return array{
     *   requestedUrl: string,
     *   finalUrl: string,
     *   statusCode: int|null,
     *   title: string,
     *   html: string,
     *   htmlBytes: int,
     *   allowedRequests: int,
     *   blockedRequests: int
     * }
     */
    public function render(
        string $sourceCode,
        string $url,
        string $allowedDomain,
        bool $authorizationApproved,
        bool $robotsApproved,
    ): array;
}
