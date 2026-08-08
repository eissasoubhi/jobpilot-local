<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\JobSourceOccurrence;
use App\Entity\UserSettings;
use App\Service\Ai\AiOfferIntakeFilter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class JobCatalogProfileCleanupService
{
    public const CONFIRMATION = 'CLEAN_NO_MATCH';

    /** @var list<string> */
    private const DISPOSABLE_APPLICATION_STATUSES = [
        'DRAFT',
        'MISSING_CV',
        'READY_TO_SUBMIT',
        'IGNORED_NOT_MATCH',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private LocalDataService $data,
        private AiOfferIntakeFilter $aiFilter,
        #[Autowire('%private_dir%')]
        private string $privateDir,
    ) {
    }

    /**
     * @return array{
     *   busy: bool,
     *   scannedOffers: int,
     *   deletedOffers: int,
     *   deletedApplications: int,
     *   deletedOccurrences: int,
     *   deletedMarkedNotMatch: int,
     *   deletedStoredAiNoMatch: int,
     *   deletedAiNoMatch: int,
     *   protectedHistoryOffers: int,
     *   keptOffers: int
     * }
     */
    public function cleanup(string $confirmation): array
    {
        if (!hash_equals(self::CONFIRMATION, trim($confirmation))) {
            throw new \InvalidArgumentException('Confirmation de nettoyage invalide.');
        }

        $this->ensurePrivateDirectory();
        $lock = fopen($this->privateDir.'/job-search-sync.lock', 'c+');
        if ($lock === false) {
            throw new \RuntimeException('Impossible d’ouvrir le verrou de synchronisation des offres.');
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return $this->emptyResult(true);
        }

        try {
            $settings = $this->data->settings();
            $offers = array_values(array_filter(
                $this->em->getRepository(JobOffer::class)->findAll(),
                static fn (mixed $offer): bool => $offer instanceof JobOffer,
            ));
            $applications = array_values(array_filter(
                $this->em->getRepository(Application::class)->findAll(),
                static fn (mixed $application): bool => $application instanceof Application,
            ));
            $occurrences = array_values(array_filter(
                $this->em->getRepository(JobSourceOccurrence::class)->findAll(),
                static fn (mixed $occurrence): bool => $occurrence instanceof JobSourceOccurrence,
            ));

            $applicationsByOffer = $this->groupApplications($applications);
            $occurrencesByOffer = $this->groupOccurrences($occurrences);
            $result = $this->emptyResult(false);
            $result['scannedOffers'] = count($offers);

            foreach ($offers as $offer) {
                $offerKey = spl_object_id($offer);
                $linkedApplications = $applicationsByOffer[$offerKey] ?? [];

                if ($this->hasProtectedApplicationHistory($linkedApplications)) {
                    ++$result['protectedHistoryOffers'];
                    ++$result['keptOffers'];
                    continue;
                }

                $deleteReason = null;
                if ($this->isMarkedNotMatch($linkedApplications)) {
                    $deleteReason = 'manual';
                } elseif ($this->hasStoredHighConfidenceNoMatch($offer, $settings)) {
                    $deleteReason = 'stored-ai';
                } elseif ($this->aiFilter->rejection($offer, $settings) !== null) {
                    $deleteReason = 'ai';
                }

                if ($deleteReason === null) {
                    ++$result['keptOffers'];
                    continue;
                }

                foreach ($linkedApplications as $application) {
                    $this->em->remove($application);
                    ++$result['deletedApplications'];
                }
                foreach ($occurrencesByOffer[$offerKey] ?? [] as $occurrence) {
                    $this->em->remove($occurrence);
                    ++$result['deletedOccurrences'];
                }
                $this->em->remove($offer);
                ++$result['deletedOffers'];

                if ($deleteReason === 'manual') {
                    ++$result['deletedMarkedNotMatch'];
                } elseif ($deleteReason === 'stored-ai') {
                    ++$result['deletedStoredAiNoMatch'];
                } else {
                    ++$result['deletedAiNoMatch'];
                }
            }

            $this->em->flush();
            $this->em->clear();

            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param list<Application> $applications */
    private function hasProtectedApplicationHistory(array $applications): bool
    {
        foreach ($applications as $application) {
            if (!in_array($application->getStatus(), self::DISPOSABLE_APPLICATION_STATUSES, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Application> $applications */
    private function isMarkedNotMatch(array $applications): bool
    {
        foreach ($applications as $application) {
            if ($application->getStatus() === 'IGNORED_NOT_MATCH') {
                return true;
            }
        }

        return false;
    }

    private function hasStoredHighConfidenceNoMatch(JobOffer $offer, UserSettings $settings): bool
    {
        $offerData = $offer->toArray();
        $reasons = is_array($offerData['scoreReasons'] ?? null) ? $offerData['scoreReasons'] : [];
        $confidence = null;
        $decision = null;
        $hasConcreteEvidence = $offer->getScore() < $settings->getMatchingThreshold();

        foreach ($reasons as $reason) {
            if (!is_string($reason)) {
                continue;
            }

            if (preg_match('/^Analyse IA\s*:\s*(MATCH|REVIEW|NO_MATCH)\s*[·-]\s*confiance\s+(\d{1,3})%/iu', $reason, $matches) === 1) {
                $decision = strtoupper($matches[1]);
                $confidence = max(0, min(100, (int) $matches[2]));
            }

            if (str_starts_with($reason, 'Prérequis principaux manquants : ')
                || str_starts_with($reason, 'Conflits détectés : ')) {
                $hasConcreteEvidence = true;
            }
        }

        return $decision === 'NO_MATCH'
            && $confidence !== null
            && $confidence >= (int) round(AiOfferIntakeFilter::MINIMUM_REJECTION_CONFIDENCE * 100)
            && $hasConcreteEvidence;
    }

    /**
     * @param list<Application> $applications
     * @return array<int, list<Application>>
     */
    private function groupApplications(array $applications): array
    {
        $grouped = [];
        foreach ($applications as $application) {
            $grouped[spl_object_id($application->getJobOffer())][] = $application;
        }

        return $grouped;
    }

    /**
     * @param list<JobSourceOccurrence> $occurrences
     * @return array<int, list<JobSourceOccurrence>>
     */
    private function groupOccurrences(array $occurrences): array
    {
        $grouped = [];
        foreach ($occurrences as $occurrence) {
            $grouped[spl_object_id($occurrence->getJobOffer())][] = $occurrence;
        }

        return $grouped;
    }

    /**
     * @return array{
     *   busy: bool,
     *   scannedOffers: int,
     *   deletedOffers: int,
     *   deletedApplications: int,
     *   deletedOccurrences: int,
     *   deletedMarkedNotMatch: int,
     *   deletedStoredAiNoMatch: int,
     *   deletedAiNoMatch: int,
     *   protectedHistoryOffers: int,
     *   keptOffers: int
     * }
     */
    private function emptyResult(bool $busy): array
    {
        return [
            'busy' => $busy,
            'scannedOffers' => 0,
            'deletedOffers' => 0,
            'deletedApplications' => 0,
            'deletedOccurrences' => 0,
            'deletedMarkedNotMatch' => 0,
            'deletedStoredAiNoMatch' => 0,
            'deletedAiNoMatch' => 0,
            'protectedHistoryOffers' => 0,
            'keptOffers' => 0,
        ];
    }

    private function ensurePrivateDirectory(): void
    {
        if (!is_dir($this->privateDir) && !mkdir($this->privateDir, 0700, true) && !is_dir($this->privateDir)) {
            throw new \RuntimeException('Impossible de créer le dossier privé JobPilot.');
        }
    }
}
