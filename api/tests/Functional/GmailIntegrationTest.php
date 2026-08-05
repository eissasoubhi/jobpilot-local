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
        self::assertFalse($status['connected']);
        self::assertFalse($status['readPermission']);
        self::assertSame('Gmail n’est pas connecté.', $status['readPermissionMessage']);
        self::assertFalse($status['sendPermission']);
        self::assertSame('Gmail n’est pas connecté.', $status['sendPermissionMessage']);
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
        self::assertSame(0, $status['lastSync']['found']);
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

    public function testSyncExplainsThatGmailMustBeConnected(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/integrations/gmail/sync');

        self::assertResponseStatusCodeSame(409);
        $response = $this->decode($client->getResponse()->getContent());
        self::assertSame('Gmail n’est pas connecté.', $response['error']);
    }

    public function testMessageFiltersReturnAJsonList(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/integrations/gmail/messages?category=INTERVIEW_REQUEST&actionRequired=true&processed=false');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->decode($client->getResponse()->getContent()));
    }

    public function testTestSendRejectsAnInvalidDestinationBeforeCallingGmail(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/integrations/gmail/test-send', [
            'recipient' => 'adresse-invalide',
            'applicationId' => 1,
        ]);

        self::assertResponseStatusCodeSame(400);
        $response = $this->decode($client->getResponse()->getContent());
        self::assertSame('Adresse e-mail de test invalide.', $response['error']);
    }

    public function testTestSendExplainsThatGmailMustBeConnected(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/integrations/gmail/test-send', [
            'recipient' => 'destination@example.com',
            'applicationId' => 1,
        ]);

        self::assertResponseStatusCodeSame(409);
        $response = $this->decode($client->getResponse()->getContent());
        self::assertSame(
            'Gmail n’est pas connecté. Connecte Gmail avant de lancer le test.',
            $response['error'],
        );
    }

    public function testTestSendRejectsMalformedJsonWithAJsonError(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/integrations/gmail/test-send',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{invalid-json',
        );

        self::assertResponseStatusCodeSame(400);
        $response = $this->decode($client->getResponse()->getContent());
        self::assertSame('La requête d’envoi de test est invalide.', $response['error']);
    }

    /** @return array<string|int, mixed> */
    private function decode(string|false $content): array
    {
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
