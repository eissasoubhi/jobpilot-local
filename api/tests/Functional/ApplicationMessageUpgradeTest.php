<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Application;
use App\Entity\CandidateProfile;
use App\Entity\CvDocument;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApplicationMessageUpgradeTest extends WebTestCase
{
    public function testListingApplicationsUpgradesTheOldGeneratedTemplate(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->configureProfile($em);

        $cv = new CvDocument(
            'CV Symfony upgrade test',
            'CV_Aissa_Symfony.pdf',
            'message-upgrade-cv.pdf',
            'fr',
            'application/pdf',
            1024,
        );
        $job = (new JobOffer())->fill([
            'title' => 'Développeur confirmé PHP Symfony upgrade test',
            'company' => 'Signe +',
            'location' => 'Orléans',
            'contractType' => 'CDI',
            'workMode' => 'Hybride',
            'language' => 'fr',
            'description' => 'Développement PHP Symfony API Platform Doctrine et Docker.',
        ]);
        $application = (new Application($job))->prepare(
            $cv,
            "Bonjour,\n\nJe suis intéressé par le poste de Développeur confirmé PHP Symfony chez Signe +. J’ai 11 ans d’expérience en développement web, avec une forte expertise en PHP/Symfony et sur les frameworks frontend modernes. Je suis disponible immédiatement.\n\nBien cordialement,\nAissa Soubhi",
            "Madame, Monsieur,\n\nAncienne lettre générée.",
            null,
        );

        $em->persist($cv);
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
        self::assertStringContainsString('correspond directement à mon parcours', $item['message']);
        self::assertStringContainsString('API Platform, Symfony, PHP et Doctrine', $item['message']);
        self::assertStringContainsString('Vous trouverez mon CV en pièce jointe.', $item['message']);
        self::assertStringNotContainsString('Je suis intéressé par le poste de', $item['message']);
        self::assertStringNotContainsString('Ancienne lettre générée', $item['message']);
        self::assertStringStartsWith('Madame, Monsieur,', $item['coverLetter']);

        $em->clear();
        $stored = $em->getRepository(Application::class)->find($applicationId);
        self::assertInstanceOf(Application::class, $stored);
        self::assertSame($item['message'], $stored->getMessage());
    }

    public function testListingApplicationsDoesNotOverwriteACustomMessage(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->configureProfile($em);

        $cv = new CvDocument(
            'CV custom message test',
            'CV_Custom.pdf',
            'custom-message-cv.pdf',
            'fr',
            'application/pdf',
            1024,
        );
        $job = (new JobOffer())->fill([
            'title' => 'Développeur Symfony custom message test',
            'company' => 'Entreprise',
            'language' => 'fr',
            'description' => 'PHP Symfony',
        ]);
        $customMessage = "Bonjour,\n\nMessage personnalisé écrit manuellement.\n\nBien cordialement,\nAissa Soubhi";
        $application = (new Application($job))->prepare(
            $cv,
            $customMessage,
            'Lettre personnalisée',
            null,
        );

        $em->persist($cv);
        $em->persist($job);
        $em->persist($application);
        $em->flush();
        $applicationId = $application->getId();

        $client->request('GET', '/api/applications');

        self::assertResponseIsSuccessful();
        $em->clear();
        $stored = $em->getRepository(Application::class)->find($applicationId);
        self::assertInstanceOf(Application::class, $stored);
        self::assertSame($customMessage, $stored->getMessage());
        self::assertSame('Lettre personnalisée', $stored->getCoverLetter());
    }

    private function configureProfile(EntityManagerInterface $em): void
    {
        $profile = $em->getRepository(CandidateProfile::class)->findOneBy([]) ?? new CandidateProfile();
        $profile->fill([
            'fullName' => 'Aissa Soubhi',
            'availability' => 'Immédiate',
            'yearsOfExperience' => 11,
        ]);
        $em->persist($profile);
        $em->flush();
    }
}
