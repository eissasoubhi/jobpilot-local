<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\JobSourceOccurrence;
use App\Entity\UserSettings;
use App\Service\Ai\AiJobMatchAnalysis;
use App\Service\Ai\AiJobMatchAnalyzerInterface;
use App\Service\Ai\AiMatchingConfigurationStore;
use App\Service\Ai\AiOfferIntakeFilter;
use App\Service\JobCatalogProfileCleanupService;
use App\Service\LocalDataService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class JobCatalogProfileCleanupServiceTest extends TestCase
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

    public function testCleanupDeletesOnlySafeNoMatchesAndProtectsTreatedHistory(): void
    {
        $settings = (new UserSettings())->fill([
            'targetJobs' => ['Backend PHP Symfony Developer'],
            'skills' => ['PHP', 'Symfony', 'React'],
            'matchingThreshold' => 50,
        ]);

        $manualNoMatch = $this->offer('Développeur Java Spring', 25, ['Score local faible']);
        $storedNoMatch = $this->offer('Python Backend Engineer', 18, [
            'Analyse IA : NO_MATCH · confiance 96%',
            'Stack principale détectée par IA : Python, Django',
            'Prérequis principaux manquants : Python, Django',
            'Conflits détectés : Le profil principal demandé est Python.',
        ]);
        $protectedNoMatch = $this->offer('Java Backend Engineer', 12, [
            'Analyse IA : NO_MATCH · confiance 98%',
            'Prérequis principaux manquants : Java, Spring Boot',
        ]);
        $matching = $this->offer('Senior PHP Symfony Developer', 92, [
            'Analyse IA : MATCH · confiance 95%',
            'Stack principale détectée par IA : PHP, Symfony',
        ]);

        $ignoredApplication = (new Application($manualNoMatch))->fill(['status' => 'IGNORED_NOT_MATCH']);
        $submittedApplication = (new Application($protectedNoMatch))->fill(['status' => 'SUBMITTED']);

        $occurrences = [
            new JobSourceOccurrence($manualNoMatch, 'source-a', 'Source A', 'manual-no-match'),
            new JobSourceOccurrence($storedNoMatch, 'source-a', 'Source A', 'stored-no-match'),
            new JobSourceOccurrence($protectedNoMatch, 'source-a', 'Source A', 'protected-no-match'),
            new JobSourceOccurrence($matching, 'source-a', 'Source A', 'matching'),
        ];

        $offerRepository = $this->createMock(EntityRepository::class);
        $offerRepository->method('findAll')->willReturn([$manualNoMatch, $storedNoMatch, $protectedNoMatch, $matching]);
        $applicationRepository = $this->createMock(EntityRepository::class);
        $applicationRepository->method('findAll')->willReturn([$ignoredApplication, $submittedApplication]);
        $occurrenceRepository = $this->createMock(EntityRepository::class);
        $occurrenceRepository->method('findAll')->willReturn($occurrences);
        $settingsRepository = $this->createMock(EntityRepository::class);
        $settingsRepository->method('findOneBy')->willReturn($settings);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(static fn (string $class): EntityRepository => match ($class) {
            JobOffer::class => $offerRepository,
            Application::class => $applicationRepository,
            JobSourceOccurrence::class => $occurrenceRepository,
            UserSettings::class => $settingsRepository,
            default => throw new \LogicException('Unexpected repository '.$class),
        });

        $removed = [];
        $em->expects(self::exactly(5))->method('remove')->willReturnCallback(static function (object $entity) use (&$removed): void {
            $removed[] = $entity;
        });
        $em->expects(self::once())->method('flush');
        $em->expects(self::once())->method('clear');

        $this->directory = sys_get_temp_dir().'/jobpilot-profile-cleanup-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $configuration = new AiMatchingConfigurationStore(
            $this->directory,
            'test-encryption-key',
            false,
            '',
            'gemini-3.5-flash-lite',
        );
        $analyzer = new class implements AiJobMatchAnalyzerInterface {
            public function analyze(JobOffer $job, UserSettings $settings): ?AiJobMatchAnalysis
            {
                throw new \LogicException('AI must not be called when disabled.');
            }
        };
        $aiFilter = new AiOfferIntakeFilter($configuration, $analyzer);
        $service = new JobCatalogProfileCleanupService(
            $em,
            new LocalDataService($em),
            $aiFilter,
            $this->directory,
        );

        $result = $service->cleanup(JobCatalogProfileCleanupService::CONFIRMATION);

        self::assertFalse($result['busy']);
        self::assertSame(4, $result['scannedOffers']);
        self::assertSame(2, $result['deletedOffers']);
        self::assertSame(1, $result['deletedApplications']);
        self::assertSame(2, $result['deletedOccurrences']);
        self::assertSame(1, $result['deletedMarkedNotMatch']);
        self::assertSame(1, $result['deletedStoredAiNoMatch']);
        self::assertSame(0, $result['deletedAiNoMatch']);
        self::assertSame(1, $result['protectedHistoryOffers']);
        self::assertSame(2, $result['keptOffers']);
        self::assertContains($manualNoMatch, $removed);
        self::assertContains($storedNoMatch, $removed);
        self::assertNotContains($protectedNoMatch, $removed);
        self::assertNotContains($matching, $removed);
    }

    public function testCleanupRequiresExactConfirmation(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->directory = sys_get_temp_dir().'/jobpilot-profile-cleanup-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $configuration = new AiMatchingConfigurationStore(
            $this->directory,
            'test-encryption-key',
            false,
            '',
            'gemini-3.5-flash-lite',
        );
        $analyzer = new class implements AiJobMatchAnalyzerInterface {
            public function analyze(JobOffer $job, UserSettings $settings): ?AiJobMatchAnalysis
            {
                return null;
            }
        };
        $service = new JobCatalogProfileCleanupService(
            $em,
            new LocalDataService($em),
            new AiOfferIntakeFilter($configuration, $analyzer),
            $this->directory,
        );

        $this->expectException(\InvalidArgumentException::class);
        $service->cleanup('wrong');
    }

    /** @param list<string> $reasons */
    private function offer(string $title, int $score, array $reasons): JobOffer
    {
        $offer = (new JobOffer())->fill([
            'source' => 'Test',
            'title' => $title,
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'HYBRID',
            'description' => $title.' mission description',
        ]);
        $offer->setEvaluation('fr', $score, $reasons, null, null, 'PREPARED', null);

        return $offer;
    }
}
