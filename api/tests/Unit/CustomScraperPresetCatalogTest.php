<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\CustomScraperPresetCatalog;
use PHPUnit\Framework\TestCase;

final class CustomScraperPresetCatalogTest extends TestCase
{
    public function testCatalogSeparatesAuthorizationRequiredAndAssistedOnlySourcesWhileReviewIsFresh(): void
    {
        $presets = (new CustomScraperPresetCatalog(null, '2026-08-10'))->all();

        self::assertCount(6, $presets);
        $bySlug = [];
        foreach ($presets as $preset) {
            $bySlug[$preset['slug']] = $preset;
            self::assertSame('2026-08-10', $preset['reviewedAt']);
            self::assertSame('2026-11-08', $preset['reviewDueAt']);
            self::assertTrue($preset['reviewFresh']);
            self::assertSame(90, $preset['reviewTtlDays']);
            self::assertNotSame('', trim((string) $preset['termsUrl']));
            self::assertNotSame('', trim((string) $preset['reason']));
            self::assertNotSame('', trim((string) $preset['recommendedAction']));
            self::assertTrue($preset['gmailSupported']);
            self::assertNotSame('', trim((string) $preset['gmailPlatformCode']));
        }

        self::assertSame(CustomScraperPresetCatalog::STATUS_AUTHORIZATION_REQUIRED, $bySlug['apec-php-symfony']['complianceStatus']);
        self::assertTrue($bySlug['apec-php-symfony']['canPrefill']);
        self::assertSame('apec', $bySlug['apec-php-symfony']['gmailPlatformCode']);
        self::assertSame(CustomScraperPresetCatalog::STATUS_AUTHORIZATION_REQUIRED, $bySlug['lehibou-symfony']['complianceStatus']);
        self::assertTrue($bySlug['lehibou-symfony']['canPrefill']);

        foreach (['free-work-symfony', 'welcome-to-the-jungle', 'hellowork-php', 'lesjeudis'] as $slug) {
            self::assertSame(CustomScraperPresetCatalog::STATUS_ASSISTED_ONLY, $bySlug[$slug]['complianceStatus']);
            self::assertFalse($bySlug[$slug]['canPrefill']);
            self::assertTrue($bySlug[$slug]['gmailSupported']);
        }
    }

    public function testExpiredReviewBlocksPresetPrefillWithoutChangingAssistedGmailSupport(): void
    {
        $presets = (new CustomScraperPresetCatalog(null, '2026-11-09'))->all();
        $bySlug = [];
        foreach ($presets as $preset) {
            $bySlug[$preset['slug']] = $preset;
            self::assertFalse($preset['reviewFresh']);
            self::assertFalse($preset['canPrefill']);
            self::assertTrue($preset['gmailSupported']);
        }

        self::assertSame(CustomScraperPresetCatalog::STATUS_AUTHORIZATION_REQUIRED, $bySlug['apec-php-symfony']['complianceStatus']);
        self::assertSame('2026-11-08', $bySlug['apec-php-symfony']['reviewDueAt']);
        self::assertSame(CustomScraperPresetCatalog::STATUS_AUTHORIZATION_REQUIRED, $bySlug['lehibou-symfony']['complianceStatus']);
    }

    public function testReviewIsStillFreshOnTheDueDate(): void
    {
        $presets = (new CustomScraperPresetCatalog(null, '2026-11-08'))->all();

        foreach ($presets as $preset) {
            self::assertTrue($preset['reviewFresh']);
        }
    }
}
