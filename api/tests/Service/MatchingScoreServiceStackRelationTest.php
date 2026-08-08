<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\MatchingScoreService;
use PHPUnit\Framework\TestCase;

final class MatchingScoreServiceStackRelationTest extends TestCase
{
    private MatchingScoreService $service;
    private UserSettings $phpSettings;

    protected function setUp(): void
    {
        $this->service = new MatchingScoreService();
        $this->phpSettings = (new UserSettings())->fill([
            'targetJobs' => ['Senior PHP Symfony Developer', 'Backend PHP Developer'],
            'skills' => ['PHP', 'Symfony', 'React', 'Vue'],
        ]);
    }

    public function testExplicitPhpOrPythonAlternativeRemainsAValidPhpProfile(): void
    {
        $result = $this->service->evaluate($this->job(
            'Senior Backend PHP or Python Developer',
            'Two equivalent primary stacks are accepted: PHP Symfony or Python FastAPI. The developer joins the product API team.',
        ), $this->phpSettings);

        self::assertGreaterThan(45, $result['score']);
        self::assertContains('Stack principale détectée : PHP/Symfony ou Python', $result['reasons']);
        self::assertContains('Relation des stacks détectée : alternatives explicites', $result['reasons']);
        self::assertNotContains('Conflit de stack principale avec le profil : score plafonné à 45/100', $result['reasons']);
    }

    public function testMandatoryPhpAndDotNetAreCumulativeRequirements(): void
    {
        $result = $this->service->evaluate($this->job(
            'Senior PHP Symfony and .NET Developer',
            'PHP Symfony and .NET C# are both mandatory for this role. The engineer works on both application stacks.',
        ), $this->phpSettings);

        self::assertSame(60, $result['score']);
        self::assertContains('Stack principale détectée : PHP/Symfony ou .NET/C#', $result['reasons']);
        self::assertContains('Relation des stacks détectée : exigences cumulatives obligatoires', $result['reasons']);
        self::assertContains('PHP requis avec une autre stack principale : score plafonné à 60/100', $result['reasons']);
    }

    public function testMandatoryPhpAndGoAreCumulativeAcrossOtherBackendFamilies(): void
    {
        $result = $this->service->evaluate($this->job(
            'Senior PHP Backend Developer',
            'Both PHP and Go are mandatory for the backend role. Symfony is used on the PHP services and Go on the new services.',
        ), $this->phpSettings);

        self::assertSame(60, $result['score']);
        self::assertContains('Stack principale détectée : PHP/Symfony ou Go', $result['reasons']);
        self::assertContains('Relation des stacks détectée : exigences cumulatives obligatoires', $result['reasons']);
    }

    public function testNiceToHaveAlternativeDoesNotOverrideTheActualJavaPrimaryStack(): void
    {
        $result = $this->service->evaluate($this->job(
            'Senior Java Backend Developer',
            'Java Spring is the mandatory core backend stack. PHP or Python is only a nice-to-have for occasional legacy integrations.',
        ), $this->phpSettings);

        self::assertLessThanOrEqual(45, $result['score']);
        self::assertContains('Stack principale détectée : Java/Spring', $result['reasons']);
        self::assertNotContains('Relation des stacks détectée : alternatives explicites', $result['reasons']);
        self::assertContains('Conflit de stack principale avec le profil : score plafonné à 45/100', $result['reasons']);
    }

    public function testExplicitMixedTargetIsNotCappedWhenCandidateReallyTargetsBothStacks(): void
    {
        $settings = (new UserSettings())->fill([
            'targetJobs' => ['Senior PHP Java Developer'],
            'skills' => ['PHP', 'Java'],
        ]);
        $result = $this->service->evaluate($this->job(
            'Senior PHP Java Developer',
            'PHP and Java are both required for the position. The role owns services in both stacks.',
        ), $settings);

        self::assertGreaterThan(60, $result['score']);
        self::assertContains('Relation des stacks détectée : exigences cumulatives obligatoires', $result['reasons']);
        self::assertNotContains('PHP requis avec une autre stack principale : score plafonné à 60/100', $result['reasons']);
    }

    private function job(string $title, string $description): JobOffer
    {
        return (new JobOffer())->fill([
            'title' => $title,
            'description' => $description,
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybrid',
        ]);
    }
}
