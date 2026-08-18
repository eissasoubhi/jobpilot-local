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

    public function testItBuildsOneConciseEmailWithoutConcatenatingTheCoverLetter(): void
    {
        file_put_contents($this->uploadDir.'/stored-cv.pdf', '%PDF-test-content');
        $cv = $this->cv();
        $application = $this->application($cv);

        $factory = new ApplicationEmailFactory(new CvStorage($this->uploadDir));
        $email = $factory->create($application);

        self::assertSame('Candidature – Développeur Symfony Senior', $email['subject']);
        self::assertSame(
            "Bonjour,\n\nLe poste correspond directement à mon parcours. Vous trouverez mon CV en pièce jointe.\n\nConcernant la rémunération, ma proposition est de 500 € HT/jour.\n\nBien cordialement,\nDemo Candidate",
            $email['body'],
        );
        self::assertStringNotContainsString('---', $email['body']);
        self::assertStringNotContainsString('Voici ma lettre de motivation', $email['body']);
        self::assertSame(1, substr_count($email['body'], 'Bien cordialement'));
        self::assertSame(['CV_Demo_Symfony.pdf'], $email['attachmentNames']);
        self::assertSame($this->uploadDir.'/stored-cv.pdf', $email['attachments'][0]['path']);
        self::assertSame('CV_Demo_Symfony.pdf', $email['attachments'][0]['filename']);
        self::assertSame('application/pdf', $email['attachments'][0]['mimeType']);
    }

    public function testItLeavesTheMessageUnchangedWhenCompensationIsNotProvided(): void
    {
        file_put_contents($this->uploadDir.'/stored-cv.pdf', '%PDF-test-content');
        $cv = $this->cv();
        $job = (new JobOffer())->fill([
            'title' => 'Backend Developer',
            'description' => 'Backend role.',
        ]);
        $application = (new Application($job))->prepare(
            $cv,
            "Hello,\n\nMy CV is attached.\n\nBest regards,\nDemo Candidate",
            'This separate cover letter must not be sent.',
            null,
        );
        $factory = new ApplicationEmailFactory(new CvStorage($this->uploadDir));

        $email = $factory->create($application);

        self::assertSame(
            "Hello,\n\nMy CV is attached.\n\nBest regards,\nDemo Candidate",
            $email['body'],
        );
        self::assertStringNotContainsString('separate cover letter', $email['body']);
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
            'CV_Demo_Symfony.pdf',
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
            "Bonjour,\n\nLe poste correspond directement à mon parcours. Vous trouverez mon CV en pièce jointe.\n\nBien cordialement,\nDemo Candidate",
            "Madame, Monsieur,\n\nVoici ma lettre de motivation.",
            '500 € HT/jour',
        );
    }
}
