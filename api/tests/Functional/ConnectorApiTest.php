<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConnectorApiTest extends WebTestCase
{
    public function testConnectorRegistryIsExposedAndCanBeDisabled(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/connectors');
        self::assertResponseIsSuccessful();
        $connectors = $this->decode($client->getResponse()->getContent());
        self::assertCount(3, $connectors);

        $byCode = [];
        foreach ($connectors as $connector) {
            $byCode[$connector['code']] = $connector;
        }

        self::assertSame('API', $byCode['arbeitnow']['mode']);
        self::assertSame('API', $byCode['adzuna']['mode']);
        self::assertSame('GMAIL', $byCode['gmail']['mode']);
        self::assertFalse($byCode['arbeitnow']['configured']);
        self::assertFalse($byCode['adzuna']['configured']);
        self::assertFalse($byCode['gmail']['configured']);
        self::assertStringContainsString('Connecte Gmail', $byCode['gmail']['configurationMessage']);

        $client->jsonRequest('PATCH', '/api/connectors/arbeitnow', ['enabled' => false]);
        self::assertResponseIsSuccessful();
        $disabled = $this->decode($client->getResponse()->getContent());
        self::assertFalse($disabled['enabled']);
        self::assertSame('DISABLED', $disabled['status']);

        $client->request('POST', '/api/connectors/arbeitnow/sync');
        self::assertResponseIsSuccessful();
        $sync = $this->decode($client->getResponse()->getContent());
        self::assertTrue($sync['skipped']);

        $client->jsonRequest('PATCH', '/api/connectors/arbeitnow', ['enabled' => true]);
        self::assertResponseIsSuccessful();
        $enabled = $this->decode($client->getResponse()->getContent());
        self::assertTrue($enabled['enabled']);
        self::assertSame('MISCONFIGURED', $enabled['status']);

        $client->request('GET', '/api/connectors/history');
        self::assertResponseIsSuccessful();
        $history = $this->decode($client->getResponse()->getContent());
        self::assertIsArray($history);
    }

    /** @return array<string|int, mixed> */
    private function decode(string|false $content): array
    {
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
