<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Application;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OfferAvailabilityApiTest extends WebTestCase
{
    public function testReadyApplicationCanMarkItsOfferUnavailable(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $job = (new JobOffer())->fill([
            'source' => 'Test',
            'title' => 'Senior Symfony Developer',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'description' => 'Mission Symfony.',
            'status' => 'PREPARED',
            'publishedAt' => '2026-08-10T12:00:00+02:00',
        ]);
        $application = (new Application($job))->fill([
            'status' => 'READY_TO_SUBMIT',
            'message' => 'Bonjour',
            'coverLetter' => 'Lettre',
        ]);
        $em->persist($job);
        $em->persist($application);
        $em->flush();

        $client->request('POST', sprintf('/api/applications/%d/offer-unavailable', $application->getId()));

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('OFFER_UNAVAILABLE', $payload['status']);
        self::assertSame('UNAVAILABLE', $payload['jobOffer']['status']);

        $em->refresh($application);
        $em->refresh($job);
        self::assertSame('OFFER_UNAVAILABLE', $application->getStatus());
        self::assertSame('UNAVAILABLE', $job->getStatus());
    }

    public function testSubmittedApplicationCannotBeMarkedUnavailable(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $job = (new JobOffer())->fill([
            'source' => 'Test',
            'title' => 'Already submitted role',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'description' => 'Mission Symfony.',
            'status' => 'PREPARED',
        ]);
        $application = (new Application($job))->fill(['status' => 'SUBMITTED']);
        $em->persist($job);
        $em->persist($application);
        $em->flush();

        $client->request('POST', sprintf('/api/applications/%d/offer-unavailable', $application->getId()));

        self::assertResponseStatusCodeSame(409);
        self::assertSame('SUBMITTED', $application->getStatus());
        self::assertSame('PREPARED', $job->getStatus());
    }
}
