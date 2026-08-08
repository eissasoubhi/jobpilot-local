<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\JobSourceOccurrence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class JobCatalogResetService
{
    public const CONFIRMATION = 'RESET_OFFERS';

    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%private_dir%')]
        private string $privateDir,
    ) {
    }

    /**
     * @return array{busy: bool, deletedOffers: int, deletedApplications: int, deletedOccurrences: int}
     */
    public function reset(string $confirmation): array
    {
        if (!hash_equals(self::CONFIRMATION, trim($confirmation))) {
            throw new \InvalidArgumentException('Confirmation de réinitialisation invalide.');
        }

        $this->ensurePrivateDirectory();
        $lock = fopen($this->privateDir.'/job-search-sync.lock', 'c+');
        if ($lock === false) {
            throw new \RuntimeException('Impossible d’ouvrir le verrou de synchronisation des offres.');
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return [
                'busy' => true,
                'deletedOffers' => 0,
                'deletedApplications' => 0,
                'deletedOccurrences' => 0,
            ];
        }

        try {
            $applications = $this->em->getRepository(Application::class)->findAll();
            $occurrences = $this->em->getRepository(JobSourceOccurrence::class)->findAll();
            $offers = $this->em->getRepository(JobOffer::class)->findAll();

            foreach ($applications as $application) {
                if ($application instanceof Application) {
                    $this->em->remove($application);
                }
            }
            foreach ($occurrences as $occurrence) {
                if ($occurrence instanceof JobSourceOccurrence) {
                    $this->em->remove($occurrence);
                }
            }
            foreach ($offers as $offer) {
                if ($offer instanceof JobOffer) {
                    $this->em->remove($offer);
                }
            }

            // Doctrine flushes the scheduled deletes in a transaction. Explicitly removing
            // dependent entities first keeps the reset independent from database-specific
            // cascade behavior.
            $this->em->flush();
            $this->em->clear();

            return [
                'busy' => false,
                'deletedOffers' => count($offers),
                'deletedApplications' => count($applications),
                'deletedOccurrences' => count($occurrences),
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function ensurePrivateDirectory(): void
    {
        if (!is_dir($this->privateDir) && !mkdir($this->privateDir, 0700, true) && !is_dir($this->privateDir)) {
            throw new \RuntimeException('Impossible de créer le dossier privé JobPilot.');
        }
    }
}
