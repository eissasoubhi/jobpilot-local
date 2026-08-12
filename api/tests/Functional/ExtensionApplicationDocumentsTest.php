<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ExtensionApplicationDocumentsTest extends WebTestCase
{
    public function testExtensionResolvesPreparedApplicationDocumentsWithoutGuessing(): void
    {
        $client = static::createClient();

        $temporaryCv = tempnam(sys_get_temp_dir(), 'jobpilot-extension-cv-');
        self::assertIsString($temporaryCv);
        file_put_contents($temporaryCv, "%PDF-1.4\n% Autofill document test CV\n%%EOF\n");
        $uploadedCv = new UploadedFile($temporaryCv, 'cv-autofill-fr.pdf', 'application/pdf', null, true);

        $client->request('POST', '/api/cvs', [
            'name' => 'CV Autofill FR',
            'language' => 'fr',
            'category' => 'Full-Stack',
            'tags' => 'PHP, Symfony, React',
            'defaultForLanguage' => 'true',
        ], [
            'file' => $uploadedCv,
        ]);
        self::assertResponseStatusCodeSame(201);

        $sourceUrl = 'https://jobs.example.test/senior-symfony-autofill';
        $client->jsonRequest('POST', '/api/jobs', [
            'source' => 'Extension test',
            'sourceCode' => 'extension-test',
            'sourceUrl' => $sourceUrl,
            'externalId' => 'autofill-document-test',
            'title' => 'Senior Symfony React Developer',
            'company' => 'Example Autofill',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'description' => 'Nous recherchons un développeur senior PHP Symfony React TypeScript Docker API Platform pour renforcer une équipe produit.',
            'salaryMin' => 50_000,
            'salaryMax' => 60_000,
        ]);
        self::assertResponseStatusCodeSame(201);
        $job = $this->decode($client);
        self::assertIsInt($job['id']);

        $client->request('GET', '/api/applications');
        self::assertResponseIsSuccessful();
        $applications = $this->decode($client);
        $application = null;
        foreach ($applications as $candidate) {
            if (($candidate['jobOffer']['id'] ?? null) === $job['id']) {
                $application = $candidate;
                break;
            }
        }

        self::assertIsArray($application);
        self::assertSame('cv-autofill-fr.pdf', $application['cvDocument']['originalName']);
        self::assertNotSame('', trim((string) $application['coverLetter']));

        $client->jsonRequest('POST', '/api/extension/application-documents', [
            'jobOfferId' => $job['id'],
        ]);
        self::assertResponseIsSuccessful();
        $context = $this->decode($client);

        self::assertSame(1, $context['schemaVersion']);
        self::assertSame('job-offer-id', $context['matchedBy']);
        self::assertSame($job['id'], $context['jobOfferId']);
        self::assertSame($application['id'], $context['applicationId']);
        self::assertSame('cv-autofill-fr.pdf', $context['cv']['filename']);
        self::assertSame('application/pdf', $context['cv']['mimeType']);
        self::assertSame('/api/cvs/'.$context['cv']['id'].'/download', $context['cv']['downloadUrl']);
        self::assertNotSame('', trim((string) $context['coverLetter']['text']));
        self::assertSame(['pdf', 'docx', 'txt'], array_column($context['coverLetter']['variants'], 'format'));

        $client->jsonRequest('POST', '/api/extension/application-documents', [
            'applicationId' => $application['id'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('application-id', $this->decode($client)['matchedBy']);

        $client->jsonRequest('POST', '/api/extension/application-documents', [
            'url' => $sourceUrl,
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('source-url', $this->decode($client)['matchedBy']);
    }

    public function testExtensionRefusesMissingOrUnknownDocumentContext(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/extension/application-documents', []);
        self::assertResponseStatusCodeSame(400);

        $client->jsonRequest('POST', '/api/extension/application-documents', [
            'jobOfferId' => 999_999_999,
            'url' => 'https://jobs.example.test/not-known',
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    /** @return array<string|int, mixed> */
    private function decode(object $client): array
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
