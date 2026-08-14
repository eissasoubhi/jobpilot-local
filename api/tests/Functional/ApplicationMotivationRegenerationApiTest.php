<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Application;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApplicationMotivationRegenerationApiTest extends WebTestCase
{
    public function testMessageCanBeRegeneratedWithACustomCharacterLimitWithoutChangingStatus(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $application = $this->persistApplication($em, 'message-'.bin2hex(random_bytes(4)));
        $initialStatus = $application->getStatus();

        $client->jsonRequest('POST', sprintf('/api/applications/%d/message/regenerate', $application->getId()), [
            'maxCharacters' => 240,
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($initialStatus, $payload['status']);
        self::assertNotSame('Ancien message.', $payload['message']);
        self::assertLessThanOrEqual(240, mb_strlen($payload['message']));
        self::assertStringContainsString('Symfony', $payload['message']);

        $em->refresh($application);
        self::assertSame($payload['message'], $application->getMessage());
    }

    public function testCoverLetterCanBeRegeneratedWithACustomCharacterLimitAndBecomesGeneratedAgain(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $application = $this->persistApplication($em, 'letter-'.bin2hex(random_bytes(4)));
        $application->editCoverLetter('Version modifiée manuellement.');
        $em->flush();
        self::assertTrue($application->isCoverLetterManuallyEdited());
        $initialStatus = $application->getStatus();

        $client->jsonRequest('POST', sprintf('/api/applications/%d/cover-letter/regenerate', $application->getId()), [
            'maxCharacters' => 650,
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($initialStatus, $payload['status']);
        self::assertFalse($payload['coverLetterManuallyEdited']);
        self::assertNull($payload['coverLetterEditedAt']);
        self::assertLessThanOrEqual(650, mb_strlen($payload['coverLetter']));
        self::assertStringContainsString('Symfony', $payload['coverLetter']);

        $em->refresh($application);
        self::assertSame($application->getGeneratedCoverLetter(), $application->getCoverLetter());
        self::assertFalse($application->isCoverLetterManuallyEdited());
    }

    public function testInvalidCharacterLimitsAreRejected(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $application = $this->persistApplication($em, 'invalid-'.bin2hex(random_bytes(4)));

        $client->jsonRequest('POST', sprintf('/api/applications/%d/message/regenerate', $application->getId()), [
            'maxCharacters' => 20,
        ]);
        self::assertResponseStatusCodeSame(400);

        $client->jsonRequest('POST', sprintf('/api/applications/%d/cover-letter/regenerate', $application->getId()), [
            'maxCharacters' => 'beaucoup',
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testSubmittedApplicationContentCannotBeRegenerated(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $application = $this->persistApplication($em, 'submitted-'.bin2hex(random_bytes(4)));
        $application->fill(['status' => 'SUBMITTED']);
        $em->flush();

        $client->jsonRequest('POST', sprintf('/api/applications/%d/message/regenerate', $application->getId()), [
            'maxCharacters' => 400,
        ]);
        self::assertResponseStatusCodeSame(409);

        $client->jsonRequest('POST', sprintf('/api/applications/%d/cover-letter/regenerate', $application->getId()), [
            'maxCharacters' => 1_200,
        ]);
        self::assertResponseStatusCodeSame(409);
    }

    private function persistApplication(EntityManagerInterface $em, string $externalId): Application
    {
        $job = (new JobOffer())->fill([
            'source' => 'Motivation regeneration test',
            'sourceCode' => 'motivation-regeneration-test',
            'externalId' => $externalId,
            'title' => 'Développeur PHP Symfony',
            'company' => 'Example Corp',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'language' => 'fr',
            'description' => 'Mission Symfony PHP API Platform et React.',
            'status' => 'PREPARED',
        ]);
        $application = new Application($job);
        $application->prepare(null, 'Ancien message.', 'Ancienne lettre générée.', null);

        $em->persist($job);
        $em->persist($application);
        $em->flush();
        self::assertNotNull($application->getId());

        return $application;
    }
}
