<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Application;
use App\Entity\CvDocument;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApplicationCvRepairTest extends WebTestCase
{
    public function testListingApplicationsAttachesTheBestActiveCvToExistingPreparedApplications(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $cv = new CvDocument(
            'CV English React',
            'CV_Aissa_React_EN.pdf',
            'functional-repair-cv.pdf',
            'en',
            'application/pdf',
            1024,
        );
        $cv->configure([
            'defaultForLanguage' => true,
            'tags' => ['react', 'frontend'],
        ]);

        $job = (new JobOffer())->fill([
            'title' => 'Frontend React Engineer repair test',
            'company' => 'Repair Test Company',
            'location' => 'Remote',
            'contractType' => 'CDI',
            'workMode' => 'Remote',
            'language' => 'en',
            'description' => 'React frontend role used to verify automatic CV repair.',
        ]);
        $application = (new Application($job))->prepare(
            null,
            'Test message',
            'Test cover letter',
            null,
        );

        self::assertSame('MISSING_CV', $application->getStatus());

        $em->persist($cv);
        $em->persist($job);
        $em->persist($application);
        $em->flush();
        $applicationId = $application->getId();
        $cvId = $cv->getId();

        $client->request('GET', '/api/applications');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        $items = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $repaired = array_values(array_filter(
            $items,
            static fn (array $item): bool => $item['id'] === $applicationId,
        ));

        self::assertCount(1, $repaired);
        self::assertSame('READY_TO_SUBMIT', $repaired[0]['status']);
        self::assertSame($cvId, $repaired[0]['cvDocument']['id']);

        $em->clear();
        $stored = $em->getRepository(Application::class)->find($applicationId);
        self::assertInstanceOf(Application::class, $stored);
        self::assertSame('READY_TO_SUBMIT', $stored->getStatus());
        self::assertSame($cvId, $stored->getCvDocument()?->getId());
    }

    public function testListingApplicationsMarksUnrepairableEntriesAsMissingCv(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $job = (new JobOffer())->fill([
            'title' => 'No matching language CV repair test',
            'company' => 'Repair Test Company',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'language' => 'zz',
            'description' => 'No CV can match this artificial language.',
        ]);
        $application = new Application($job);
        $application->fill([
            'status' => 'READY_TO_SUBMIT',
            'message' => 'Legacy message',
            'coverLetter' => 'Legacy cover letter',
        ]);

        $em->persist($job);
        $em->persist($application);
        $em->flush();
        $applicationId = $application->getId();

        $client->request('GET', '/api/applications');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        $items = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $item = array_values(array_filter(
            $items,
            static fn (array $candidate): bool => $candidate['id'] === $applicationId,
        ))[0] ?? null;

        self::assertIsArray($item);
        self::assertSame('MISSING_CV', $item['status']);
        self::assertNull($item['cvDocument']);
    }
}
