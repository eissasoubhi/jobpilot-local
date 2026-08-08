<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\JobSourceOccurrence;
use App\Entity\UserSettings;
use App\Service\Ai\AiJobMatchAnalyzerInterface;
use App\Service\Ai\AiMatchingConfigurationStore;
use App\Service\Ai\AiOfferIntakeFilter;
use App\Service\JobProfileCleanupService;
use App\Service\LocalDataService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class JobProfileCleanupServiceTest extends TestCase
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

    public function testManualNoMatchIsDeletedWithoutCallingAi(): void
    {
        $offer = (new JobOffer())->fill(['title' => 'Backend Developer', 'description' => 'Legacy role']);
        $application = (new Application($offer))->fill(['status' => 'IGNORED_NOT_MATCH']);
        $settings = (new UserSettings())->fill(['matchingThreshold' => 50]);

        [$service, $em] = $this->service(
            [$offer],
            [$application],
            [],
            $settings,
            new class implements AiJobMatchAnalyzerInterface {
                public function analyze(JobOffer $job, UserSettings $settings): ?\App\Service\Ai\AiJobMatchAnalysis
                {
                    throw new \RuntimeException('AI must not be called for a manually rejected offer.');
                }
            },
        );

        $em->expects($this->exactly(2))->method('remove');
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('clear');

        $result = $service->cleanup(JobProfileCleanupService::CONFIRMATION);

        self::assertFalse($result['busy']);
        self::assertSame(1, $result['deletedOffers']);
        self::assertSame(1, $result['deletedApplications']);
        self::assertSame(1, $result['manuallyRejected']);
        self::assertSame(0, $result['aiChecks']);
    }

    public function testProcessedApplicationHistoryProtectsOfferFromCleanup(): void
    {
        $offer = (new JobOffer())->fill(['title' => 'Java Developer', 'description' => 'Java Spring']);
        $application = (new Application($offer))->fill(['status' => 'SUBMITTED']);
        $settings = (new UserSettings())->fill(['matchingThreshold' => 50]);

        [$service, $em] = $this->service(
            [$offer],
            [$application],
            [],
            $settings,
            new class implements AiJobMatchAnalyzerInterface {
                public function analyze(JobOffer $job, UserSettings $settings): ?\App\Service\Ai\AiJobMatchAnalysis
                {
                    throw new \RuntimeException('AI must not be called for protected application history.');
                }
            },
        );

        $em->expects($this->never())->method('remove');
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('clear');

        $result = $service->cleanup(JobProfileCleanupService::CONFIRMATION);

        self::assertSame(0, $result['deletedOffers']);
        self::assertSame(1, $result['protectedHistory']);
        self::assertSame(1, $result['kept']);
        self::assertSame(0, $result['aiChecks']);
    }

    public function testStoredHighConfidenceAiNoMatchIsReusedWithoutNewAiCheck(): void
    {
        $offer = (new JobOffer())->fill([
            'title' => 'Python Backend Engineer',
            'description' => 'Python and Django are mandatory.',
        ]);
        $offer->setEvaluation('fr', 18, [
            'Analyse IA : NO_MATCH · confiance 96%',
            'Stack principale détectée par IA : Python, Django',
            'Conflits détectés : stack principale hors profil',
        ], null, null, 'DISCOVERED', null);
        $settings = (new UserSettings())->fill(['matchingThreshold' => 50]);

        [$service, $em] = $this->service(
            [$offer],
            [],
            [],
            $settings,
            new class implements AiJobMatchAnalyzerInterface {
                public function analyze(JobOffer $job, UserSettings $settings): ?\App\Service\Ai\AiJobMatchAnalysis
                {
                    throw new \RuntimeException('Stored high-confidence NO_MATCH must be reused.');
                }
            },
        );

        $em->expects($this->once())->method('remove')->with($offer);
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('clear');

        $result = $service->cleanup(JobProfileCleanupService::CONFIRMATION);

        self::assertSame(1, $result['deletedOffers']);
        self::assertSame(1, $result['reusedStoredAi']);
        self::assertSame(0, $result['aiChecks']);
    }

    public function testInvalidConfirmationDoesNothing(): void
    {
        $settings = (new UserSettings())->fill(['matchingThreshold' => 50]);
        [$service, $em] = $this->service(
            [],
            [],
            [],
            $settings,
            new class implements AiJobMatchAnalyzerInterface {
                public function analyze(JobOffer $job, UserSettings $settings): ?\App\Service\Ai\AiJobMatchAnalysis
                {
                    return null;
                }
            },
        );

        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);
        $service->cleanup('wrong');
    }

    /**
     * @param list<JobOffer> $offers
     * @param list<Application> $applications
     * @param list<JobSourceOccurrence> $occurrences
     * @return array{JobProfileCleanupService, EntityManagerInterface}
     */
    private function service(
        array $offers,
        array $applications,
        array $occurrences,
        UserSettings $settings,
        AiJobMatchAnalyzerInterface $analyzer,
    ): array {
        $offerRepository = $this->createMock(EntityRepository::class);
        $offerRepository->method('findAll')->willReturn($offers);

        $applicationRepository = $this->createMock(EntityRepository::class);
        $applicationRepository->method('findBy')->willReturnCallback(
            static fn (array $criteria): array => array_values(array_filter(
                $applications,
                static fn (Application $application): bool => $application->getJobOffer() === ($criteria['jobOffer'] ?? null),
            )),
        );

        $occurrenceRepository = $this->createMock(EntityRepository::class);
        $occurrenceRepository->method('findBy')->willReturnCallback(
            static fn (array $criteria): array => array_values(array_filter(
                $occurrences,
                static fn (JobSourceOccurrence $occurrence): bool => $occurrence->getJobOffer() === ($criteria['jobOffer'] ?? null),
            )),
        );

        $settingsRepository = $this->createMock(EntityRepository::class);
        $settingsRepository->method('findOneBy')->willReturn($settings);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(static function (string $class) use (
            $offerRepository,
            $applicationRepository,
            $occurrenceRepository,
            $settingsRepository,
        ): EntityRepository {
            return match ($class) {
                JobOffer::class => $offerRepository,
                Application::class => $applicationRepository,
                JobSourceOccurrence::class => $occurrenceRepository,
                UserSettings::class => $settingsRepository,
                default => throw new \RuntimeException('Unexpected repository '.$class),
            };
        });

        $this->directory = sys_get_temp_dir().'/jobpilot-profile-cleanup-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $configuration = new AiMatchingConfigurationStore(
            $this->directory,
            'test-encryption-key',
            true,
            'gemini-test-key',
            'gemini-3.5-flash-lite',
        );
        $filter = new AiOfferIntakeFilter($configuration, $analyzer);
        $data = new LocalDataService($em);

        return [new JobProfileCleanupService($em, $data, $filter, $this->directory), $em];
    }
}
