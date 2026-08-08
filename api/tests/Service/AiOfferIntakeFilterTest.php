<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\Ai\AiJobMatchAnalysis;
use App\Service\Ai\AiJobMatchAnalyzerInterface;
use App\Service\Ai\AiMatchingConfigurationStore;
use App\Service\Ai\AiOfferIntakeFilter;
use PHPUnit\Framework\TestCase;

final class AiOfferIntakeFilterTest extends TestCase
{
    /** @var list<string> */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            foreach (glob($directory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
        $this->directories = [];
    }

    public function testRejectsOnlyHighConfidenceNoMatchWithConcreteEvidence(): void
    {
        $analysis = $this->analysis(
            score: 18,
            confidence: 0.96,
            decision: 'NO_MATCH',
            missingMustHaves: ['Java', 'Spring Boot'],
            conflicts: ['Primary backend stack is Java/Spring.'],
        );
        $analyzer = $this->analyzer($analysis);
        $filter = new AiOfferIntakeFilter($this->configuration(true, 'gemini-test-key'), $analyzer);

        self::assertSame($analysis, $filter->rejection($this->job(), $this->settings()));
        self::assertSame(1, $analyzer->calls);
    }

    public function testKeepsLowConfidenceOrAmbiguousNoMatch(): void
    {
        $analysis = $this->analysis(
            score: 20,
            confidence: 0.70,
            decision: 'NO_MATCH',
            missingMustHaves: ['Python'],
            conflicts: ['Primary stack is Python.'],
        );
        $analyzer = $this->analyzer($analysis);
        $filter = new AiOfferIntakeFilter($this->configuration(true, 'gemini-test-key'), $analyzer);

        self::assertNull($filter->rejection($this->job(), $this->settings()));
        self::assertSame(1, $analyzer->calls);
    }

    public function testKeepsInconsistentNoMatchWithoutConcreteMismatchEvidence(): void
    {
        $analysis = $this->analysis(
            score: 82,
            confidence: 0.97,
            decision: 'NO_MATCH',
            missingMustHaves: [],
            conflicts: [],
        );
        $analyzer = $this->analyzer($analysis);
        $filter = new AiOfferIntakeFilter($this->configuration(true, 'gemini-test-key'), $analyzer);

        self::assertNull($filter->rejection($this->job(), $this->settings()));
    }

    public function testDoesNotCallAiWhenMatchingIsDisabledOrKeyIsMissing(): void
    {
        $analysis = $this->analysis(
            score: 10,
            confidence: 0.99,
            decision: 'NO_MATCH',
            missingMustHaves: ['Go'],
            conflicts: ['Primary stack is Go.'],
        );

        $disabledAnalyzer = $this->analyzer($analysis);
        $disabled = new AiOfferIntakeFilter($this->configuration(false, 'gemini-test-key'), $disabledAnalyzer);
        self::assertNull($disabled->rejection($this->job(), $this->settings()));
        self::assertSame(0, $disabledAnalyzer->calls);

        $missingKeyAnalyzer = $this->analyzer($analysis);
        $missingKey = new AiOfferIntakeFilter($this->configuration(true, ''), $missingKeyAnalyzer);
        self::assertNull($missingKey->rejection($this->job(), $this->settings()));
        self::assertSame(0, $missingKeyAnalyzer->calls);
    }

    private function job(): JobOffer
    {
        return (new JobOffer())->fill([
            'source' => 'Test',
            'sourceCode' => 'test',
            'externalId' => 'job-1',
            'title' => 'Senior Java Backend Developer',
            'description' => 'Java and Spring Boot are mandatory. PHP is mentioned only for a legacy service.',
            'contractType' => 'CDI',
            'location' => 'Paris',
        ]);
    }

    private function settings(): UserSettings
    {
        return (new UserSettings())->fill([
            'targetJobs' => ['Senior PHP Symfony Developer', 'React Developer'],
            'skills' => ['PHP', 'Symfony', 'React', 'TypeScript'],
            'matchingThreshold' => 50,
        ]);
    }

    private function configuration(bool $enabled, string $apiKey): AiMatchingConfigurationStore
    {
        $directory = sys_get_temp_dir().'/jobpilot-ai-intake-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);
        $this->directories[] = $directory;

        return new AiMatchingConfigurationStore(
            $directory,
            'test-encryption-key',
            $enabled,
            $apiKey,
            'gemini-3.5-flash-lite',
        );
    }

    private function analyzer(AiJobMatchAnalysis $analysis): AiJobMatchAnalyzerInterface
    {
        return new class($analysis) implements AiJobMatchAnalyzerInterface {
            public int $calls = 0;

            public function __construct(private readonly AiJobMatchAnalysis $analysis)
            {
            }

            public function analyze(JobOffer $job, UserSettings $settings): ?AiJobMatchAnalysis
            {
                ++$this->calls;

                return $this->analysis;
            }
        };
    }

    /** @param list<string> $missingMustHaves @param list<string> $conflicts */
    private function analysis(
        int $score,
        float $confidence,
        string $decision,
        array $missingMustHaves,
        array $conflicts,
    ): AiJobMatchAnalysis {
        return new AiJobMatchAnalysis(
            $score,
            $confidence,
            $decision,
            'Backend Developer',
            ['Java', 'Spring Boot'],
            ['PHP'],
            ['Java', 'Spring Boot'],
            [],
            $missingMustHaves,
            $conflicts,
            'The requested primary stack does not match the configured candidate profile.',
            'CONTEXTUAL',
        );
    }
}
