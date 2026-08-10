<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

final class CustomScraperBrowserRenderCoordinator
{
    public function __construct(
        private BrowserRenderClientInterface $client,
        private CustomScraperBrowserRenderPolicy $policy,
    ) {
    }

    /**
     * @return array{
     *   rendered: bool,
     *   result: array<string, mixed>|null
     * }
     */
    public function renderIfAllowed(
        string $sourceCode,
        string $url,
        string $allowedDomain,
        string $configuredMode,
        string $recommendedMode,
        bool $authorizationApproved,
        bool $robotsApproved,
    ): array {
        if (!$this->policy->shouldRender(
            $configuredMode,
            $recommendedMode,
            $this->client->isConfigured(),
            $authorizationApproved,
            $robotsApproved,
        )) {
            return ['rendered' => false, 'result' => null];
        }

        return [
            'rendered' => true,
            'result' => $this->client->render(
                $sourceCode,
                $url,
                $allowedDomain,
                $authorizationApproved,
                $robotsApproved,
            ),
        ];
    }
}
