<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\MatchingScoreService;
use PHPUnit\Framework\TestCase;

final class MatchingScoreServiceTitleCompatibilityTest extends TestCase
{
    private MatchingScoreService $service;

    protected function setUp(): void
    {
        $this->service = new MatchingScoreService();
    }

    public function testGenericBackendDeveloperWordsCannotCreateAStrongMatchAlone(): void
    {
        $result = $this->service->evaluate(
            $this->job(
                'Senior Web Backend Developer',
                'Build web APIs and backend services for a business application.',
            ),
            $this->settings(['Senior Backend Developer'], ['PHP', 'Symfony']),
        );

        self::assertLessThan(60, $result['score']);
        self::assertContains('Compatibilité intitulé : 10/35', $result['reasons']);
        self::assertFalse($result['hardRejected']);
    }

    public function testTechnologyMentionedOnlyInDescriptionDoesNotBecomeATitleMatch(): void
    {
        $result = $this->service->evaluate(
            $this->job(
                'Senior Backend Developer',
                'React is mentioned in documentation for a separate internal interface.',
            ),
            $this->settings(['React Developer'], []),
        );

        self::assertLessThan(60, $result['score']);
        self::assertContains('Compatibilité intitulé : 5/35', $result['reasons']);
        self::assertNotContains('Compatibilité intitulé : 35/35', $result['reasons']);
    }

    public function testSpecificPhpSymfonyTitleKeepsFullTitleCompatibility(): void
    {
        $result = $this->service->evaluate(
            $this->job(
                'Senior PHP Symfony Developer',
                'PHP Symfony Doctrine and API Platform are the core technologies.',
            ),
            $this->settings(
                ['Senior PHP Symfony Developer', 'Backend PHP Developer'],
                ['PHP', 'Symfony', 'Doctrine', 'API Platform'],
            ),
        );

        self::assertGreaterThanOrEqual(60, $result['score']);
        self::assertContains('Compatibilité intitulé : 35/35', $result['reasons']);
        self::assertContains('Stack principale détectée : PHP/Symfony', $result['reasons']);
    }

    public function testSpecificReactTitleRemainsDiscriminatingForFrontendProfiles(): void
    {
        $result = $this->service->evaluate(
            $this->job(
                'React Developer',
                'Build product interfaces with React and TypeScript.',
            ),
            $this->settings(['React Developer'], ['React', 'TypeScript']),
        );

        self::assertGreaterThanOrEqual(60, $result['score']);
        self::assertContains('Compatibilité intitulé : 35/35', $result['reasons']);
    }

    /** @param list<string> $targetJobs @param list<string> $skills */
    private function settings(array $targetJobs, array $skills): UserSettings
    {
        return (new UserSettings())->fill([
            'targetJobs' => $targetJobs,
            'skills' => $skills,
        ]);
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
