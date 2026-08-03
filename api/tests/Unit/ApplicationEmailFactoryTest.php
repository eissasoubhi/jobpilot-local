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
    public function testItBuildsTheExactAutomaticEmailPayload(): void
    {
        $cv = new CvDocument(
            'CV Symfony',
            'CV_Aissa_Symfony.pdf',
            'stored-cv.pdf',
            'fr',
            'application/pdf',
            1234,
        );
        $job = (new JobOffer())->fill([
            'title' => 'Développeur Symfony Senior',
            'company' => 'Entreprise Test',
            'location' => 'Paris',
            'contractType' => 'Freelance',
            'workMode' => 'Hybride',
            'description' => 'Mission Symfony.',
        ]);
        $job->setEvaluation('fr', 82, [], 500, null, 'PREPARED', $cv);

        $application = (new Application($job))->prepare(
            $cv,
            "Bonjour,\n\nJe suis intéressé par cette mission.",
            "Madame, Monsieur,\n\nVoici ma lettre de motivation.",
            '500 € HT/jour',
        );

        $factory = new ApplicationEmailFactory(new CvStorage('/tmp/jobpilot-email-test'));
        $email = $factory->create($application);

        self::assertSame('Candidature – Développeur Symfony Senior', $email['subject']);
        self::assertSame(
            "Bonjour,\n\nJe suis intéressé par cette mission.\n\n---\n\nMadame, Monsieur,\n\nVoici ma lettre de motivation.\n\n---\n\nRémunération : 500 € HT/jour",
            $email['body'],
        );
        self::assertSame(['CV_Aissa_Symfony.pdf'], $email['attachmentNames']);
        self::assertSame('/tmp/jobpilot-email-test/stored-cv.pdf', $email['attachments'][0]['path']);
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
        $factory = new ApplicationEmailFactory(new CvStorage('/tmp/jobpilot-email-test'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aucun CV n’est sélectionné');

        $factory->create($application);
    }
}
