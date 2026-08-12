<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CustomScraperMultiSearchPreviewApiTest extends WebTestCase
{
    public function testPreviewRequiresAnExistingAuthorizedSourceBeforeAnyNetworkCall(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/custom-scrapers/999999/search-preview');
        self::assertResponseStatusCodeSame(404);

        $client->jsonRequest('POST', '/api/custom-scrapers', [
            'name' => 'Multi Search Preview Guard',
            'listingUrl' => 'https://multisearch-preview.example.com/jobs',
            'searchUrlTemplate' => 'https://multisearch-preview.example.com/search?q={keyword}',
            'searchKeywords' => ['PHP', 'Symfony'],
            'mode' => 'HTTP',
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-12',
            'authorizationReference' => 'Autorisation de test.',
            'maxPages' => 2,
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = $this->decode($client->getResponse()->getContent());
        $id = $created['id'];
        self::assertIsInt($id);

        $client->jsonRequest('PATCH', sprintf('/api/custom-scrapers/%d', $id), [
            'authorizationConfirmed' => false,
        ]);
        self::assertResponseIsSuccessful();

        $client->request('POST', sprintf('/api/custom-scrapers/%d/search-preview', $id));
        self::assertResponseStatusCodeSame(400);
        $blocked = $this->decode($client->getResponse()->getContent());
        self::assertStringContainsString('autorisation', mb_strtolower((string) ($blocked['error'] ?? '')));

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
