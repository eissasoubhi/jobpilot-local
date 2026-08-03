<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GmailIntegrationTest extends WebTestCase
{
    public function testStatusExplainsMissingOAuthConfiguration(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/integrations/gmail/status');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        $status = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertFalse($status['configured']);
        self::assertContains('GOOGLE_CLIENT_ID', $status['missingVariables']);
        self::assertContains('GOOGLE_CLIENT_SECRET', $status['missingVariables']);
        self::assertSame(
            'http://localhost:8080/api/integrations/gmail/callback',
            $status['redirectUri'],
        );
        self::assertSame(
            'http://localhost:8080/api/integrations/gmail/start',
            $status['startUrl'],
        );
    }

    public function testStartReturnsToSettingsInsteadOfA500WhenConfigurationIsMissing(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/integrations/gmail/start');

        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        self::assertIsString($location);
        self::assertStringStartsWith(
            'http://localhost:3000/parametres?gmail_error=',
            $location,
        );
        self::assertStringContainsString('GOOGLE_CLIENT_ID', urldecode($location));
        self::assertStringContainsString('GOOGLE_CLIENT_SECRET', urldecode($location));
    }
}
