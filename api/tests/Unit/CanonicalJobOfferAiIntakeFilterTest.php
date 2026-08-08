<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\JobCatalog\Application\CanonicalJobMatcher;
use App\JobCatalog\Application\CanonicalJobOfferService;
use App\JobCatalog\Application\ProfileFilteredJobOffer;
use App\Service\Ai\AiJobMatchAnalysis;
use App\Service\Ai\AiJobMatchAnalyzerInterface;
use App\Service\Ai\AiMatchingConfigurationStore;
use App\Service\Ai\AiOfferIntakeFilter;
use App\Service\JobProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class CanonicalJobOfferAiIntakeFilterTest extends TestCase
{
    private ?string $directory = null;

    protected function tearDown(): void
    {
        if ($this->directory !== null) {
            foreach (glob($this->directory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->directory);
        }
    }

    public function testHighConfidenceNoMatchIsDiscardedBeforeAnyPersistence(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $repository->method('findBy')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $analysis = new AiJobMatchAnalysis(
            14,
            0.98,
            'NO_MATCH',
            'Java Backend Developer',
            ['Java', 'Spring Boot'],
            ['PHP'],
            ['Java', 'Spring Boot'],
            [],
            ['Java', 'Spring Boot'],
            ['The role is primarily Java/Spring rather than PHP/Symfony.'],
            'PHP appears only in a legacy component.',
            'CONTEXTUAL',
        );
        $analyzer = new class($analysis) implements AiJobMatchAnalyzerInterface {
            public function __construct(private readonly AiJobMatchAnalysis $analysis)
            {
            }

            public function analyze(JobOffer $job, UserSettings $settings): ?AiJobMatchAnalysis
            {
                return $this->analysis;
            }
        };

        $this->directory = sys_get_temp_dir().'/jobpilot-canonical-intake-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $configuration = new AiMatchingConfigurationStore(
            $this->directory,
            'test-encryption-key',
            true,
            'gemini-test-key',
            'gemini-3.5-flash-lite',
        );
        $filter = new AiOfferIntakeFilter($configuration, $analyzer);
        $processor = (new \ReflectionClass(JobProcessor::class))->newInstanceWithoutConstructor();
        self::assertInstanceOf(JobProcessor::class, $processor);

        $service = new CanonicalJobOfferService(
            $em,
            new CanonicalJobMatcher($em),
            $processor,
            $filter,
        );
        $settings = (new UserSettings())->fill([
            'targetJobs' => ['Senior PHP Symfony Developer'],
            'skills' => ['PHP', 'Symfony', 'React'],
            'matchingThreshold' => 50,
        ]);

        $this->expectException(ProfileFilteredJobOffer::class);
        $service->import(
            [
                'externalId' => 'java-1',
                'sourceUrl' => 'https://example.test/jobs/java-1',
                'title' => 'Senior Java Backend Developer',
                'company' => 'Example',
                'description' => 'Java and Spring Boot are mandatory. PHP is only a legacy service.',
                'contractType' => 'CDI',
                'location' => 'Paris',
            ],
            'test-source',
            'Test Source',
            'API',
            $settings,
            new CandidateProfile(),
            true,
        );
    }
}
