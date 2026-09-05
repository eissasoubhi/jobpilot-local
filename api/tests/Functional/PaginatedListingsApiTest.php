<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Application;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PaginatedListingsApiTest extends WebTestCase
{
    public function testJobListingSupportsBoundedPagesAndStatusFiltering(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $status = 'PAGINATED_'.strtoupper(bin2hex(random_bytes(4)));

        foreach (['First', 'Second'] as $title) {
            $em->persist((new JobOffer())->fill([
                'source' => 'Pagination test',
                'title' => $title.' '.$status,
                'company' => 'JobPilot',
                'description' => 'Offre créée pour vérifier le contrat paginé.',
                'contractType' => 'CDI',
                'status' => $status,
            ]));
        }
        $em->flush();

        $client->request('GET', sprintf('/api/jobs?status=%s&page=1&limit=1', $status));
        self::assertResponseIsSuccessful();
        $firstPage = $this->decode($client);

        self::assertCount(1, $firstPage['items']);
        self::assertSame([
            'page' => 1,
            'limit' => 1,
            'total' => 2,
            'totalPages' => 2,
        ], $firstPage['pagination']);

        $client->request('GET', sprintf('/api/jobs?status=%s&page=3&limit=1', $status));
        self::assertResponseIsSuccessful();
        $pastLastPage = $this->decode($client);
        self::assertSame([], $pastLastPage['items']);
        self::assertSame(2, $pastLastPage['pagination']['total']);
    }

    public function testApplicationListingPaginatesInTheDatabaseAndKeepsLegacyShape(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $status = 'SUBMITTED';

        foreach (['First', 'Second'] as $title) {
            $job = (new JobOffer())->fill([
                'source' => 'Pagination test',
                'title' => $title.' PAGINATED_'.$suffix,
                'company' => 'JobPilot',
                'description' => 'Offre créée pour vérifier le contrat paginé.',
            ]);
            $application = (new Application($job))->fill(['status' => $status]);
            $em->persist($job);
            $em->persist($application);
        }
        $em->flush();

        $client->request('GET', sprintf('/api/applications?status=%s&page=2&limit=1', $status));
        self::assertResponseIsSuccessful();
        $secondPage = $this->decode($client);

        self::assertCount(1, $secondPage['items']);
        self::assertSame(2, $secondPage['pagination']['page']);
        self::assertSame(1, $secondPage['pagination']['limit']);
        self::assertGreaterThanOrEqual(2, $secondPage['pagination']['total']);
        self::assertSame(
            $secondPage['pagination']['total'],
            $secondPage['pagination']['totalPages'],
        );
        self::assertSame($status, $secondPage['items'][0]['status']);

        $client->request('GET', '/api/applications?status=STATUS_THAT_DOES_NOT_EXIST');
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->decode($client));
    }

    /** @return array<string|int, mixed> */
    private function decode(object $client): array
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
