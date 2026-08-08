<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\MatchingScoreService;
use PHPUnit\Framework\TestCase;

final class MatchingScoreServicePrimaryStackTest extends TestCase
{
    private MatchingScoreService $service;
    private UserSettings $settings;

    protected function setUp(): void
    {
        $this->service = new MatchingScoreService();
        $this->settings = (new UserSettings())->fill([
            'targetJobs' => [
                'Senior PHP Symfony Developer',
                'Backend PHP Developer',
                'Backend Developer',
            ],
            'skills' => ['PHP', 'Symfony', 'React', 'API Platform'],
        ]);
    }

    public function testPrimaryJavaRoleWithIncidentalPhpMentionIsCapped(): void
    {
        $result = $this->service->evaluate($this->job(
            'Senior Backend Java Developer',
            'Nous recherchons un développeur Java Spring pour concevoir les services principaux. Une ancienne application PHP reste mentionnée dans le contexte de migration.',
        ), $this->settings);

        self::assertLessThanOrEqual(45, $result['score']);
        self::assertContains('Stack principale détectée : Java/Spring', $result['reasons']);
        self::assertContains('Conflit de stack principale avec le profil : score plafonné à 45/100', $result['reasons']);
        self::assertFalse($result['hardRejected']);
    }

    public function testPrimaryPythonRoleWithGenericBackendKeywordsIsCapped(): void
    {
        $result = $this->service->evaluate($this->job(
            'Senior Backend Developer',
            'Nous recherchons un développeur backend Python FastAPI. Vous concevrez des API web et des services distribués.',
        ), $this->settings);

        self::assertLessThanOrEqual(45, $result['score']);
        self::assertContains('Stack principale détectée : Python', $result['reasons']);
        self::assertContains('Conflit de stack principale avec le profil : score plafonné à 45/100', $result['reasons']);
    }

    public function testPrimarySymfonyRoleIsNotPenalizedForSecondaryJavaMention(): void
    {
        $result = $this->service->evaluate($this->job(
            'Senior Symfony PHP Developer',
            'Le coeur du poste est en Symfony et PHP. Une connaissance de Java est appréciée pour dialoguer avec une application tierce.',
        ), $this->settings);

        self::assertGreaterThan(45, $result['score']);
        self::assertContains('Stack principale détectée : PHP/Symfony', $result['reasons']);
        self::assertNotContains('Conflit de stack principale avec le profil : score plafonné à 45/100', $result['reasons']);
    }

    public function testGenuineJavaOrPhpPrimaryAlternativeIsNotPenalized(): void
    {
        $result = $this->service->evaluate($this->job(
            'Senior Backend Java or PHP Developer',
            'Le poste accepte deux stacks principales équivalentes : Java Spring ou PHP Symfony. Le candidat travaillera sur les API du produit.',
        ), $this->settings);

        self::assertGreaterThan(45, $result['score']);
        self::assertContains('Stack principale détectée : PHP/Symfony ou Java/Spring', $result['reasons']);
        self::assertNotContains('Conflit de stack principale avec le profil : score plafonné à 45/100', $result['reasons']);
    }

    private function job(string $title, string $description): JobOffer
    {
        return (new JobOffer())->fill([
            'title' => $title,
            'description' => $description,
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
        ]);
    }
}
