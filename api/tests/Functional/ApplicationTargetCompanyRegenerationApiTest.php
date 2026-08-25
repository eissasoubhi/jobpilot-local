<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Application;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApplicationTargetCompanyRegenerationApiTest extends WebTestCase
{
    public function testPlatformNameIsOmittedAndManualCompanyOverrideIsUsed(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $job = (new JobOffer())->fill([
            'source' => 'Indeed',
            'sourceCode' => 'indeed-assisted',
            'sourceUrl' => 'https://fr.indeed.com/viewjob?jk='.bin2hex(random_bytes(4)),
            'externalId' => 'target-company-'.bin2hex(random_bytes(4)),
            'title' => 'Développeur OIC senior H/F',
            'company' => 'Indeed',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'language' => 'fr',
            'description' => 'Mission avec PHP, Symfony et intégration.',
            'status' => 'PREPARED',
        ]);
        $application = new Application($job);
        $application->prepare(null, 'Ancien message.', 'Ancienne lettre.', null);
        $em->persist($job);
        $em->persist($application);
        $em->flush();
        self::assertNotNull($application->getId());

        $client->jsonRequest('POST', sprintf('/api/applications/%d/message/regenerate', $application->getId()), [
            'maxCharacters' => 400,
        ]);
        self::assertResponseIsSuccessful();
        $messagePayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('chez Indeed', $messagePayload['message']);

        $client->jsonRequest('POST', sprintf('/api/applications/%d/cover-letter/regenerate', $application->getId()), [
            'maxCharacters' => 1_500,
            'targetCompany' => 'Proton',
        ]);
        self::assertResponseIsSuccessful();
        $letterPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('chez Proton', $letterPayload['coverLetter']);
        self::assertStringNotContainsString('chez Indeed', $letterPayload['coverLetter']);
    }

    public function testInvalidTargetCompanyOverrideIsRejected(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $job = (new JobOffer())->fill([
            'source' => 'Indeed',
            'sourceCode' => 'indeed-assisted',
            'externalId' => 'target-company-invalid-'.bin2hex(random_bytes(4)),
            'title' => 'Développeur Symfony',
            'company' => 'Indeed',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'language' => 'fr',
            'description' => 'Symfony PHP.',
            'status' => 'PREPARED',
        ]);
        $application = new Application($job);
        $application->prepare(null, 'Ancien message.', 'Ancienne lettre.', null);
        $em->persist($job);
        $em->persist($application);
        $em->flush();

        $client->jsonRequest('POST', sprintf('/api/applications/%d/message/regenerate', $application->getId()), [
            'maxCharacters' => 400,
            'targetCompany' => ['not', 'text'],
        ]);

        self::assertResponseStatusCodeSame(400);
    }
}
