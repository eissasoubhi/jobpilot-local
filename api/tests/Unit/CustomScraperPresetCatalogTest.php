<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\CustomScraperPresetCatalog;
use PHPUnit\Framework\TestCase;

final class CustomScraperPresetCatalogTest extends TestCase
{
    public function testCatalogSeparatesAuthorizationRequiredAndAssistedOnlySources(): void
    {
        $presets = (new CustomScraperPresetCatalog())->all();

        self::assertCount(6, $presets);
        $bySlug = [];
        foreach ($presets as $preset) {
            $bySlug[$preset['slug']] = $preset;
            self::assertSame('2026-08-10', $preset['reviewedAt']);
            self::assertNotSame('', trim((string) $preset['termsUrl']));
            self::assertNotSame('', trim((string) $preset['reason']));
            self::assertNotSame('', trim((string) $preset['recommendedAction']));
        }

        self::assertSame(CustomScraperPresetCatalog::STATUS_AUTHORIZATION_REQUIRED, $bySlug['apec-php-symfony']['complianceStatus']);
        self::assertTrue($bySlug['apec-php-symfony']['canPrefill']);
        self::assertSame(CustomScraperPresetCatalog::STATUS_AUTHORIZATION_REQUIRED, $bySlug['lehibou-symfony']['complianceStatus']);
        self::assertTrue($bySlug['lehibou-symfony']['canPrefill']);

        foreach (['free-work-symfony', 'welcome-to-the-jungle', 'hellowork-php', 'lesjeudis'] as $slug) {
            self::assertSame(CustomScraperPresetCatalog::STATUS_ASSISTED_ONLY, $bySlug[$slug]['complianceStatus']);
            self::assertFalse($bySlug[$slug]['canPrefill']);
        }
    }
}
