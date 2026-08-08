<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\MatchingScoreService;
use PHPUnit\Framework\TestCase;

final class MatchingScoreServicePhpProfileTest extends TestCase
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

    public function testRubyRailsPrimaryRoleIsRecognizedAsNonPhpEvenWithPhpContext(): void
    {
        $result = $this->service->evaluate($this->job(
            'Senior Ruby on Rails Developer',
            'Ruby on Rails is the core backend stack. A legacy PHP service remains during migration.',
        ), $this->phpSettings);

        self::assertLessThanOrEqual(45, $result['score']);
        self::assertContains('Stack principale détectée : Ruby/Rails', $result['reasons']);
        self::assertContains('Profil PHP détecté : non-PHP principal', $result['reasons']);
        self::assertContains('Conflit de stack principale avec le profil : score plafonné à 45/100', $result['reasons']);
    }

    public function testNodeBackendPrimaryRoleIsRecognizedAsNonPhp(): void
    {
        $result = $this->service->evaluate($this->job(
            'Senior Node.js Backend Developer',
            'Node.js and NestJS are mandatory for the product APIs. PHP is only a nice-to-have for one old integration.',
        ), $this->phpSettings);

        self::assertLessThanOrEqual(45, $result['score']);
        self::assertContains('Stack principale détectée : Node.js/NestJS', $result['reasons']);
        self::assertContains('Profil PHP détecté : non-PHP principal', $result['reasons']);
    }

    public function testGoPrimaryRoleIsRecognizedWithoutHardcodingJavaPythonOrDotNet(): void
    {
        $result = $this->service->evaluate($this->job(
            'Senior Go Developer',
            'The core services are written in Golang. PHP appears only in an older internal tool.',
        ), $this->phpSettings);

        self::assertLessThanOrEqual(45, $result['score']);
        self::assertContains('Stack principale détectée : Go', $result['reasons']);
        self::assertContains('Profil PHP détecté : non-PHP principal', $result['reasons']);
    }

    public function testExplicitReactTargetIsNotRejectedBecauseNodeAppearsInTheDescription(): void
    {
        $settings = (new UserSettings())->fill([
            'targetJobs' => ['Senior PHP Symfony Developer', 'React Developer'],
            'skills' => ['PHP', 'Symfony', 'React', 'Next.js', 'TypeScript'],
        ]);
        $result = $this->service->evaluate($this->job(
            'Senior React Developer',
            'React, Next.js and TypeScript are the core frontend stack. The team integrates with a Node.js API maintained by another team.',
        ), $settings);

        self::assertGreaterThan(45, $result['score']);
        self::assertContains('Stack principale détectée : Node.js/NestJS', $result['reasons']);
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
            'workMode' => 'Hybrid',
        ]);
    }
}
