<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\JobSourceOccurrence;
use App\Entity\UserSettings;
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

    public function testManualNoMatchIsDeletedWithoutAnyAiCheck(): void
    {
        $offer = (new JobOffer())->fill(['title' => 'Backend Developer', 'description' => 'Legacy role']);
        $application = (new Application($offer))->fill(['status' => 'IGNORED_NOT_MATCH']);
        $settings = (new UserSettings())->fill(['matchingThreshold' => 50]);

        [$service, $em] = $this->service([$offer], [$application], [], $settings);

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

        [$service, $em] = $this->service([$offer], [$application], [], $settings);

        $em->expects($this->never())->method('remove');
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('clear');

        $result = $service->cleanup(JobProfileCleanupService::CONFIRMATION);

        self::assertSame(0, $result['deletedOffers']);
        self::assertSame(1, $result['protectedHistory']);
        self::assertSame(1, $result['kept']);
        self::assertSame(0, $result['aiChecks']);
    }

    public function testStoredHighConfidenceAiNoMatchIsReusedWithoutProviderCall(): void
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

        [$service, $em] = $this->service([$offer], [], [], $settings);

        $em->expects($this->once())->method('remove')->with($offer);
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('clear');

        $result = $service->cleanup(JobProfileCleanupService::CONFIRMATION);

        self::assertSame(1, $result['deletedOffers']);
        self::assertSame(1, $result['reusedStoredAi']);
        self::assertSame(0, $result['aiChecks']);
    }

    public function testOfferWithoutStoredSafeRejectionIsKeptInsteadOfTriggeringFreshAi(): void
    {
        $offer = (new JobOffer())->fill([
            'title' => 'Java Developer',
            'description' => 'Java and Spring Boot are mandatory.',
        ]);
        $offer->setEvaluation('fr', 35, [
            'Stack principale détectée : Java / Spring',
        ], null, null, 'DISCOVERED', null);
        $settings = (new UserSettings())->fill(['matchingThreshold' => 50]);

        [$service, $em] = $this->service([$offer], [], [], $settings);

        $em->expects($this->never())->method('remove');
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('clear');

        $result = $service->cleanup(JobProfileCleanupService::CONFIRMATION);

        self::assertSame(0, $result['deletedOffers']);
        self::assertSame(1, $result['kept']);
        self::assertSame(0, $result['aiChecks']);
    }

    public function testLowConfidenceStoredNoMatchIsKept(): void
    {
        $offer = (new JobOffer())->fill([
            'title' => 'Python Backend Engineer',
            'description' => 'Python and Django.',
        ]);
        $offer->setEvaluation('fr', 20, [
            'Analyse IA : NO_MATCH · confiance 70%',
            'Conflits détectés : stack principale hors profil',
        ], null, null, 'DISCOVERED', null);
        $settings = (new UserSettings())->fill(['matchingThreshold' => 50]);

        [$service, $em] = $this->service([$offer], [], [], $settings);

        $em->expects($this->never())->method('remove');
        $result = $service->cleanup(JobProfileCleanupService::CONFIRMATION);

        self::assertSame(0, $result['deletedOffers']);
        self::assertSame(1, $result['kept']);
    }

    public function testInvalidConfirmationDoesNothing(): void
    {
        $settings = (new UserSettings())->fill(['matchingThreshold' => 50]);
        [$service, $em] = $this->service([], [], [], $settings);

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
        $data = new LocalDataService($em);

        return [new JobProfileCleanupService($em, $data, $this->directory), $em];
    }
}
