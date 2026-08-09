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
        self::assertGreaterThanOrEqual(6, count($connectors));

        $byCode = [];
        foreach ($connectors as $connector) {
            $byCode[$connector['code']] = $connector;
        }

        self::assertSame('API', $byCode['arbeitnow']['mode']);
        self::assertSame('API', $byCode['adzuna']['mode']);
        self::assertSame('API', $byCode['france-travail']['mode']);
        self::assertSame('GMAIL', $byCode['gmail']['mode']);
        self::assertSame('RSS', $byCode['symfony-jobs']['mode']);
        self::assertSame('SCRAPING_HTTP', $byCode['le-studio-tech']['mode']);
        self::assertSame('ALLOWED', $byCode['arbeitnow']['policy']['complianceStatus']);
        self::assertSame('AUTHORIZED_ONLY', $byCode['adzuna']['policy']['complianceStatus']);
        self::assertSame('AUTHORIZED_ONLY', $byCode['france-travail']['policy']['complianceStatus']);
        self::assertSame('AUTHORIZED_ONLY', $byCode['gmail']['policy']['complianceStatus']);
        self::assertSame('ALLOWED', $byCode['symfony-jobs']['policy']['complianceStatus']);
        self::assertSame('ALLOWED', $byCode['le-studio-tech']['policy']['complianceStatus']);
        self::assertTrue($byCode['arbeitnow']['collectionAllowed']);
        self::assertTrue($byCode['symfony-jobs']['collectionAllowed']);
        self::assertTrue($byCode['le-studio-tech']['collectionAllowed']);
        self::assertSame(3, $byCode['arbeitnow']['policy']['maxRequestsPerSync']);
        self::assertSame(6, $byCode['adzuna']['policy']['maxRequestsPerSync']);
        self::assertSame(7, $byCode['france-travail']['policy']['maxRequestsPerSync']);
        self::assertSame(4, $byCode['symfony-jobs']['policy']['maxRequestsPerSync']);
        self::assertSame(16, $byCode['symfony-jobs']['policy']['dailyQuota']);
        self::assertSame(60, $byCode['le-studio-tech']['policy']['maxRequestsPerSync']);
        self::assertSame(240, $byCode['le-studio-tech']['policy']['dailyQuota']);
        self::assertTrue($byCode['le-studio-tech']['policy']['respectsRobotsTxt']);
        self::assertSame('2026-08-06', $byCode['france-travail']['policy']['reviewedAt']);
        self::assertSame('2026-08-05', $byCode['gmail']['policy']['reviewedAt']);
        self::assertSame('2026-08-05', $byCode['symfony-jobs']['policy']['reviewedAt']);
        self::assertSame('2026-08-09', $byCode['le-studio-tech']['policy']['reviewedAt']);
        self::assertFalse($byCode['arbeitnow']['configured']);
        self::assertFalse($byCode['adzuna']['configured']);
        self::assertFalse($byCode['france-travail']['configured']);
        self::assertFalse($byCode['gmail']['configured']);
        self::assertTrue($byCode['symfony-jobs']['configured']);
        self::assertTrue($byCode['le-studio-tech']['configured']);
        self::assertStringContainsString('FRANCE_TRAVAIL_CLIENT_ID', $byCode['france-travail']['configurationMessage']);
        self::assertStringContainsString('Configuration OAuth incomplète', $byCode['gmail']['configurationMessage']);
        self::assertStringContainsString('Flux RSS officiel', $byCode['symfony-jobs']['configurationMessage']);
        self::assertStringContainsString('robots.txt', $byCode['le-studio-tech']['configurationMessage']);
        self::assertNull($byCode['arbeitnow']['parserVersion']);
        self::assertSame('offres-emploi-v2', $byCode['france-travail']['parserVersion']);
        self::assertSame('syndication-v1', $byCode['symfony-jobs']['parserVersion']);
        self::assertSame('le-studio-tech-html-v1', $byCode['le-studio-tech']['parserVersion']);
        self::assertSame('NO_DATA', $byCode['symfony-jobs']['health']['status']);
        self::assertFalse($byCode['symfony-jobs']['health']['alert']);
        self::assertSame(0, $byCode['symfony-jobs']['health']['sampleSize']);
        self::assertSame(0, $byCode['symfony-jobs']['fieldQuality']['received']);
        self::assertNull($byCode['symfony-jobs']['fieldQuality']['requiredCompleteness']);
        self::assertNull($byCode['symfony-jobs']['fieldQuality']['overallCompleteness']);
        self::assertArrayHasKey('externalId', $byCode['symfony-jobs']['fieldQuality']['fields']);

        $client->jsonRequest('PATCH', '/api/connectors/arbeitnow', ['enabled' => false]);
        self::assertResponseIsSuccessful();
        $disabled = $this->decode($client->getResponse()->getContent());
        self::assertFalse($disabled['enabled']);
        self::assertSame('DISABLED', $disabled['status']);
        self::assertArrayHasKey('health', $disabled);
        self::assertArrayHasKey('fieldQuality', $disabled);

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
