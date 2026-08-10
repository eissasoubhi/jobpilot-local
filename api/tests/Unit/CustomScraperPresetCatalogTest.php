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
            self::assertSame(90, $preset['reviewDaysRemaining']);
            self::assertFalse($preset['reviewRenewalRecommended']);
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

    public function testRenewalWarningStartsExactlyFourteenDaysBeforeDueDate(): void
    {
        $fifteenDaysBefore = (new CustomScraperPresetCatalog(null, '2026-10-24'))->all();
        $fourteenDaysBefore = (new CustomScraperPresetCatalog(null, '2026-10-25'))->all();

        foreach ($fifteenDaysBefore as $preset) {
            self::assertSame(15, $preset['reviewDaysRemaining']);
            self::assertFalse($preset['reviewRenewalRecommended']);
            self::assertTrue($preset['reviewFresh']);
        }
        foreach ($fourteenDaysBefore as $preset) {
            self::assertSame(14, $preset['reviewDaysRemaining']);
            self::assertTrue($preset['reviewRenewalRecommended']);
            self::assertTrue($preset['reviewFresh']);
        }
    }

    public function testExpiredReviewBlocksPresetPrefillWithoutChangingAssistedGmailSupport(): void
    {
        $presets = (new CustomScraperPresetCatalog(null, '2026-11-09'))->all();
        $bySlug = [];
        foreach ($presets as $preset) {
            $bySlug[$preset['slug']] = $preset;
            self::assertFalse($preset['reviewFresh']);
            self::assertFalse($preset['reviewRenewalRecommended']);
            self::assertSame(0, $preset['reviewDaysRemaining']);
            self::assertFalse($preset['canPrefill']);
            self::assertTrue($preset['gmailSupported']);
        }

        self::assertSame(CustomScraperPresetCatalog::STATUS_AUTHORIZATION_REQUIRED, $bySlug['apec-php-symfony']['complianceStatus']);
        self::assertSame('2026-11-08', $bySlug['apec-php-symfony']['reviewDueAt']);
        self::assertSame(CustomScraperPresetCatalog::STATUS_AUTHORIZATION_REQUIRED, $bySlug['lehibou-symfony']['complianceStatus']);
    }

    public function testReviewIsFreshButRenewalRecommendedOnDueDate(): void
    {
        $presets = (new CustomScraperPresetCatalog(null, '2026-11-08'))->all();

        foreach ($presets as $preset) {
            self::assertTrue($preset['reviewFresh']);
            self::assertTrue($preset['reviewRenewalRecommended']);
            self::assertSame(0, $preset['reviewDaysRemaining']);
        }
    }
}
