<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Application;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApplicationCoverLetterApiTest extends WebTestCase
{
    public function testCoverLetterCanBeEditedDownloadedAndResetWithoutChangingStatus(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));

        $job = (new JobOffer())->fill([
            'source' => 'Cover letter API test',
            'sourceCode' => 'cover-letter-test',
            'externalId' => $suffix,
            'title' => 'Développeur PHP Symfony',
            'company' => 'Example Corp',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'language' => 'fr',
            'description' => 'Mission Symfony de test.',
            'status' => 'PREPARED',
        ]);
        $application = new Application($job);
        $application->prepare(null, 'Message préparé', 'Version générée initiale.', null);
        $initialStatus = $application->getStatus();

        $em->persist($job);
        $em->persist($application);
        $em->flush();
        self::assertNotNull($application->getId());
        $id = $application->getId();

        $client->jsonRequest('PATCH', sprintf('/api/applications/%d/cover-letter', $id), [
            'coverLetter' => "Version personnalisée.\nDeuxième ligne.",
        ]);
        self::assertResponseIsSuccessful();
        $edited = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame("Version personnalisée.\nDeuxième ligne.", $edited['coverLetter']);
        self::assertTrue($edited['coverLetterManuallyEdited']);
        self::assertNotEmpty($edited['coverLetterEditedAt']);
        self::assertSame($initialStatus, $edited['status']);

        $client->request('GET', sprintf('/api/applications/%d/cover-letter/download', $id));
        self::assertResponseIsSuccessful();
        self::assertSame("Version personnalisée.\nDeuxième ligne.", $client->getResponse()->getContent());
        self::assertStringContainsString('text/plain', (string) $client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString(
            'Lettre-motivation_Example-Corp_Developpeur-PHP-Symfony.txt',
            (string) $client->getResponse()->headers->get('Content-Disposition'),
        );

        $client->request('GET', sprintf('/api/applications/%d/cover-letter/download/pdf', $id));
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('%PDF-1.4', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('application/pdf', (string) $client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString(
            'Lettre-motivation_Example-Corp_Developpeur-PHP-Symfony.pdf',
            (string) $client->getResponse()->headers->get('Content-Disposition'),
        );

        $client->request('GET', sprintf('/api/applications/%d/cover-letter/download/docx', $id));
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('PK', (string) $client->getResponse()->getContent());
        self::assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            (string) $client->getResponse()->headers->get('Content-Type'),
        );
        self::assertStringContainsString(
            'Lettre-motivation_Example-Corp_Developpeur-PHP-Symfony.docx',
            (string) $client->getResponse()->headers->get('Content-Disposition'),
        );

        $client->request('POST', sprintf('/api/applications/%d/cover-letter/reset', $id));
        self::assertResponseIsSuccessful();
        $reset = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Version générée initiale.', $reset['coverLetter']);
        self::assertFalse($reset['coverLetterManuallyEdited']);
        self::assertNull($reset['coverLetterEditedAt']);
        self::assertSame($initialStatus, $reset['status']);
    }
}
