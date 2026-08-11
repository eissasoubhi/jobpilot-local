<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CustomScraperSourceApiTest extends WebTestCase
{
    public function testAuthorizedCustomScraperSourcesCanBeManaged(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/custom-scrapers');
        self::assertResponseIsSuccessful();
        self::assertIsArray($this->decode($client->getResponse()->getContent()));

        $client->jsonRequest('POST', '/api/custom-scrapers', [
            'name' => 'Example Jobs',
            'listingUrl' => 'https://jobs.example.com/offres',
            'mode' => 'AUTO',
        ]);
        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('confirmer', $client->getResponse()->getContent());

        $client->jsonRequest('POST', '/api/custom-scrapers', [
            'name' => 'Example Jobs',
            'listingUrl' => 'https://jobs.example.com/offres',
            'detailExampleUrl' => 'https://jobs.example.com/offres/123',
            'mode' => 'AUTO',
            'enabled' => true,
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-09',
            'authorizationReference' => 'CGU vérifiées manuellement par l’utilisateur.',
            'syncIntervalMinutes' => 360,
            'maxPages' => 5,
            'maxDetails' => 20,
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = $this->decode($client->getResponse()->getContent());
        self::assertIsInt($created['id']);
        self::assertSame('jobs.example.com', $created['domain']);
        self::assertSame('AUTO', $created['mode']);
        self::assertTrue($created['enabled']);
        self::assertTrue($created['authorizationConfirmed']);
        self::assertSame('2026-08-09', $created['authorizationCheckedAt']);
        self::assertSame(5, $created['maxPages']);
        self::assertSame(20, $created['maxDetails']);

        $id = $created['id'];
        $client->request('GET', '/api/connectors');
        self::assertResponseIsSuccessful();
        $connectors = $this->decode($client->getResponse()->getContent());
        $dynamic = null;
        foreach ($connectors as $connector) {
            if (is_array($connector) && ($connector['code'] ?? null) === 'custom-scraper-'.$id) {
                $dynamic = $connector;
                break;
            }
        }
        self::assertIsArray($dynamic);
        self::assertSame('Example Jobs', $dynamic['name']);
        self::assertSame('SCRAPING_HTTP', $dynamic['mode']);
        self::assertTrue($dynamic['configured']);
        self::assertTrue($dynamic['collectionAllowed']);
        self::assertSame('custom-generic-html-v4-browser', $dynamic['parserVersion']);

        $client->jsonRequest('PATCH', sprintf('/api/custom-scrapers/%d', $id), [
            'mode' => 'BROWSER',
            'syncIntervalMinutes' => 30,
            'maxPages' => 100,
            'maxDetails' => 500,
        ]);
        self::assertResponseIsSuccessful();
        $updated = $this->decode($client->getResponse()->getContent());
        self::assertSame('BROWSER', $updated['mode']);
        self::assertSame(60, $updated['syncIntervalMinutes']);
        self::assertSame(20, $updated['maxPages']);
        self::assertSame(100, $updated['maxDetails']);

        $client->jsonRequest('PATCH', sprintf('/api/custom-scrapers/%d', $id), [
            'detailExampleUrl' => 'https://other.example.net/job/123',
        ]);
        self::assertResponseStatusCodeSame(400);
        $invalidDetail = $this->decode($client->getResponse()->getContent());
        self::assertStringContainsString('même domaine', (string) ($invalidDetail['error'] ?? ''));

        $client->jsonRequest('PATCH', sprintf('/api/custom-scrapers/%d', $id), [
            'authorizationConfirmed' => false,
        ]);
        self::assertResponseIsSuccessful();
        $disabled = $this->decode($client->getResponse()->getContent());
        self::assertFalse($disabled['authorizationConfirmed']);
        self::assertFalse($disabled['enabled']);

        $client->request('POST', sprintf('/api/custom-scrapers/%d/diagnose', $id));
        self::assertResponseStatusCodeSame(400);
        $diagnosticBlocked = $this->decode($client->getResponse()->getContent());
        self::assertStringContainsString('autorisation', (string) ($diagnosticBlocked['error'] ?? ''));

        $client->request('POST', sprintf('/api/custom-scrapers/%d/preview', $id));
        self::assertResponseStatusCodeSame(400);
        $previewBlocked = $this->decode($client->getResponse()->getContent());
        self::assertStringContainsString('autorisation', (string) ($previewBlocked['error'] ?? ''));

        $client->jsonRequest('PATCH', sprintf('/api/custom-scrapers/%d', $id), [
            'enabled' => true,
        ]);
        self::assertResponseStatusCodeSame(400);

        $client->request('DELETE', sprintf('/api/custom-scrapers/%d', $id));
        self::assertResponseStatusCodeSame(204);
    }

    /** @return array<string|int, mixed> */
    private function decode(string|false $content): array
    {
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
