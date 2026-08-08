<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\JobSourceOccurrence;
use App\Service\JobCatalogResetService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class JobCatalogResetServiceTest extends TestCase
{
    private ?string $directory = null;

    protected function tearDown(): void
    {
        if ($this->directory === null) {
            return;
        }
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testResetRequiresExactConfirmation(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getRepository');
        $service = new JobCatalogResetService($em, $this->tempDirectory());

        $this->expectException(\InvalidArgumentException::class);
        $service->reset('REINITIALISER');
    }

    public function testResetDoesNothingWhileJobSyncLockIsHeld(): void
    {
        $directory = $this->tempDirectory();
        $lock = fopen($directory.'/job-search-sync.lock', 'c+');
        self::assertIsResource($lock);
        self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getRepository');
        $service = new JobCatalogResetService($em, $directory);

        try {
            self::assertSame([
                'busy' => true,
                'deletedOffers' => 0,
                'deletedApplications' => 0,
                'deletedOccurrences' => 0,
            ], $service->reset(JobCatalogResetService::CONFIRMATION));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function testResetDeletesOnlyCatalogEntitiesAndReturnsCounts(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Senior PHP Symfony Developer',
            'company' => 'Example',
            'description' => 'PHP Symfony',
        ]);
        $application = new Application($job);
        $occurrence = new JobSourceOccurrence($job, 'example', 'Example', 'offer-1');

        $applicationRepository = $this->createMock(EntityRepository::class);
        $applicationRepository->method('findAll')->willReturn([$application]);
        $occurrenceRepository = $this->createMock(EntityRepository::class);
        $occurrenceRepository->method('findAll')->willReturn([$occurrence]);
        $jobRepository = $this->createMock(EntityRepository::class);
        $jobRepository->method('findAll')->willReturn([$job]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => match ($class) {
                Application::class => $applicationRepository,
                JobSourceOccurrence::class => $occurrenceRepository,
                JobOffer::class => $jobRepository,
                default => throw new \LogicException('Unexpected repository '.$class),
            },
        );
        $em->expects(self::exactly(3))->method('remove');
        $em->expects(self::once())->method('flush');
        $em->expects(self::once())->method('clear');

        $service = new JobCatalogResetService($em, $this->tempDirectory());

        self::assertSame([
            'busy' => false,
            'deletedOffers' => 1,
            'deletedApplications' => 1,
            'deletedOccurrences' => 1,
        ], $service->reset(JobCatalogResetService::CONFIRMATION));
    }

    private function tempDirectory(): string
    {
        if ($this->directory === null) {
            $this->directory = sys_get_temp_dir().'/jobpilot-reset-'.bin2hex(random_bytes(6));
            mkdir($this->directory, 0700, true);
        }

        return $this->directory;
    }
}
