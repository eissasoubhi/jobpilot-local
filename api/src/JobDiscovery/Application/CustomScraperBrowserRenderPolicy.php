<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

use App\Entity\CustomScraperSource;

final class CustomScraperBrowserRenderPolicy
{
    public function shouldRender(
        string $configuredMode,
        string $recommendedMode,
        bool $workerConfigured,
        bool $authorizationApproved,
        bool $robotsApproved,
    ): bool {
        if (!$workerConfigured || !$authorizationApproved || !$robotsApproved) {
            return false;
        }

        $configuredMode = strtoupper(trim($configuredMode));
        $recommendedMode = strtoupper(trim($recommendedMode));

        if ($configuredMode === CustomScraperSource::MODE_HTTP) {
            return false;
        }
        if ($configuredMode === CustomScraperSource::MODE_BROWSER) {
            return true;
        }

        return $configuredMode === CustomScraperSource::MODE_AUTO
            && $recommendedMode === CustomScraperSource::MODE_BROWSER;
    }
}
