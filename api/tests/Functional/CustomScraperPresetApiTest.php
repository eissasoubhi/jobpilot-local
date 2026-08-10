<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CustomScraperPresetApiTest extends WebTestCase
{
    public function testPresetCatalogIsPubliclyReadableButDoesNotAuthorizeSources(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/custom-scrapers/presets');
        self::assertResponseIsSuccessful();
        $presets = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($presets);
        self::assertNotEmpty($presets);

        $apec = null;
        $freeWork = null;
        foreach ($presets as $preset) {
            if (($preset['slug'] ?? null) === 'apec-php-symfony') {
                $apec = $preset;
            }
            if (($preset['slug'] ?? null) === 'free-work-symfony') {
                $freeWork = $preset;
            }
        }

        self::assertIsArray($apec);
        self::assertTrue($apec['canPrefill']);
        self::assertSame('AUTHORIZATION_REQUIRED', $apec['complianceStatus']);
        self::assertIsArray($freeWork);
        self::assertFalse($freeWork['canPrefill']);
        self::assertSame('ASSISTED_ONLY', $freeWork['complianceStatus']);

        $client->jsonRequest('POST', '/api/custom-scrapers', [
            'name' => $apec['name'],
            'listingUrl' => $apec['listingUrl'],
            'mode' => $apec['mode'],
            'enabled' => true,
            'authorizationConfirmed' => false,
        ]);
        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('confirmer', (string) $client->getResponse()->getContent());
    }
}
