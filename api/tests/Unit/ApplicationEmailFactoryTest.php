<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Application;
use App\Entity\CvDocument;
use App\Entity\JobOffer;
use App\Service\ApplicationEmailFactory;
use App\Service\CvStorage;
use PHPUnit\Framework\TestCase;

final class ApplicationEmailFactoryTest extends TestCase
{
    private string $uploadDir;

    protected function setUp(): void
    {
        $this->uploadDir = sys_get_temp_dir().'/jobpilot-email-factory-'.bin2hex(random_bytes(8));
        mkdir($this->uploadDir, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uploadDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->uploadDir);
    }

    public function testItBuildsTheExactAutomaticEmailPayload(): void
    {
        file_put_contents($this->uploadDir.'/stored-cv.pdf', '%PDF-test-content');
        $cv = $this->cv();
        $application = $this->application($cv);

        $factory = new ApplicationEmailFactory(new CvStorage($this->uploadDir));
        $email = $factory->create($application);

        self::assertSame('Candidature – Développeur Symfony Senior', $email['subject']);
        self::assertSame(
            "Bonjour,\n\nJe suis intéressé par cette mission.\n\n---\n\nMadame, Monsieur,\n\nVoici ma lettre de motivation.\n\n---\n\nRémunération : 500 € HT/jour",
            $email['body'],
        );
        self::assertSame(['CV_Aissa_Symfony.pdf'], $email['attachmentNames']);
        self::assertSame($this->uploadDir.'/stored-cv.pdf', $email['attachments'][0]['path']);
        self::assertSame('CV_Aissa_Symfony.pdf', $email['attachments'][0]['filename']);
        self::assertSame('application/pdf', $email['attachments'][0]['mimeType']);
    }

    public function testItRefusesAnApplicationWithoutACv(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Backend Developer',
            'description' => 'Backend role.',
        ]);
        $application = (new Application($job))->prepare(null, 'Message', 'Cover letter', null);
        $factory = new ApplicationEmailFactory(new CvStorage($this->uploadDir));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aucun CV n’est sélectionné');

        $factory->create($application);
    }

    public function testItRefusesMetadataPointingToAMissingPhysicalCv(): void
    {
        $factory = new ApplicationEmailFactory(new CvStorage($this->uploadDir));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('introuvable dans le stockage privé');

        $factory->create($this->application($this->cv()));
    }

    private function cv(): CvDocument
    {
        return new CvDocument(
            'CV Symfony',
            'CV_Aissa_Symfony.pdf',
            'stored-cv.pdf',
            'fr',
            'application/pdf',
            1234,
        );
    }

    private function application(CvDocument $cv): Application
    {
        $job = (new JobOffer())->fill([
            'title' => 'Développeur Symfony Senior',
            'company' => 'Entreprise Test',
            'location' => 'Paris',
            'contractType' => 'Freelance',
            'workMode' => 'Hybride',
            'description' => 'Mission Symfony.',
        ]);
        $job->setEvaluation('fr', 82, [], 500, null, 'PREPARED', $cv);

        return (new Application($job))->prepare(
            $cv,
            "Bonjour,\n\nJe suis intéressé par cette mission.",
            "Madame, Monsieur,\n\nVoici ma lettre de motivation.",
            '500 € HT/jour',
        );
    }
}
