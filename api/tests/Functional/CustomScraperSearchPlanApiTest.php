<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CustomScraperSearchPlanApiTest extends WebTestCase
{
    public function testSearchPlanExposesGeneratedKeywordUrlsAndRequestBudget(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/custom-scrapers', [
            'name' => 'Keyword Jobs',
            'listingUrl' => 'https://keyword-search.example.com/offres',
            'searchUrlTemplate' => 'https://keyword-search.example.com/offres?q={keyword}&sort=date',
            'searchKeywords' => ['PHP', 'Symfony', 'Vue.js', 'React.js'],
            'mode' => 'HTTP',
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-11',
            'authorizationReference' => 'Autorisation de test confirmée.',
            'maxPages' => 3,
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = $this->decode($client->getResponse()->getContent());
        $id = $created['id'];
        self::assertIsInt($id);

        $client->request('GET', sprintf('/api/custom-scrapers/%d/search-plan', $id));
        self::assertResponseIsSuccessful();
        $plan = $this->decode($client->getResponse()->getContent());

        self::assertSame($id, $plan['sourceId']);
        self::assertSame('Keyword Jobs', $plan['sourceName']);
        self::assertTrue($plan['configured']);
        self::assertSame(4, $plan['searchCount']);
        self::assertSame(3, $plan['maxPagesPerSearch']);
        self::assertSame(12, $plan['requestedMaxListingRequests']);
        self::assertSame(10, $plan['estimatedMaxListingRequests']);
        self::assertSame(10, $plan['globalPageBudget']);
        self::assertTrue($plan['budgetLimited']);
        self::assertSame([
            ['keyword' => 'PHP', 'url' => 'https://keyword-search.example.com/offres?q=PHP&sort=date', 'pageLimit' => 3],
            ['keyword' => 'Symfony', 'url' => 'https://keyword-search.example.com/offres?q=Symfony&sort=date', 'pageLimit' => 3],
            ['keyword' => 'Vue.js', 'url' => 'https://keyword-search.example.com/offres?q=Vue.js&sort=date', 'pageLimit' => 2],
            ['keyword' => 'React.js', 'url' => 'https://keyword-search.example.com/offres?q=React.js&sort=date', 'pageLimit' => 2],
        ], $plan['searches']);

        $client->request('DELETE', sprintf('/api/custom-scrapers/%d', $id));
        self::assertResponseStatusCodeSame(204);
    }

    public function testSearchPlanFallsBackToListingUrlAndMissingSourceReturns404(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/custom-scrapers', [
            'name' => 'Fallback Jobs',
            'listingUrl' => 'https://fallback.example.com/jobs',
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-11',
            'authorizationReference' => 'Autorisation de test confirmée.',
            'maxPages' => 2,
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = $this->decode($client->getResponse()->getContent());
        $id = $created['id'];
        self::assertIsInt($id);

        $client->request('GET', sprintf('/api/custom-scrapers/%d/search-plan', $id));
        self::assertResponseIsSuccessful();
        $plan = $this->decode($client->getResponse()->getContent());
        self::assertFalse($plan['configured']);
        self::assertSame(1, $plan['searchCount']);
        self::assertSame(2, $plan['requestedMaxListingRequests']);
        self::assertSame(2, $plan['estimatedMaxListingRequests']);
        self::assertSame(2, $plan['globalPageBudget']);
        self::assertFalse($plan['budgetLimited']);
        self::assertSame([
            ['keyword' => null, 'url' => 'https://fallback.example.com/jobs', 'pageLimit' => 2],
        ], $plan['searches']);

        $client->request('GET', '/api/custom-scrapers/999999/search-plan');
        self::assertResponseStatusCodeSame(404);

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
