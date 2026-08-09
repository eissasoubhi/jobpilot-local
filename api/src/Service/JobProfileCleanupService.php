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

final class JobProfileCleanupService
{
    public const CONFIRMATION = 'CLEAN_PROFILE_MISMATCHES';

    /** @var list<string> */
    private const DELETABLE_APPLICATION_STATUSES = [
        'DRAFT',
        'MISSING_CV',
        'READY_TO_SUBMIT',
        'IGNORED_NOT_MATCH',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private LocalDataService $data,
        #[Autowire('%private_dir%')]
        private string $privateDir,
    ) {
    }

    /**
     * Selectively removes only offers for which JobPilot already has enough
     * persisted evidence to decide that they are out of profile.
     *
     * This endpoint deliberately never calls the AI provider. A catalog can
     * contain hundreds of offers; doing provider calls synchronously here used
     * to keep the local HTTP server busy for a long time, consume quota, and
     * eventually make the cleanup request (and concurrent /settings/ai calls)
     * fail with HTTP 500/timeouts.
     *
     * @return array{
     *   busy: bool,
     *   scanned: int,
     *   deletedOffers: int,
     *   deletedApplications: int,
     *   deletedOccurrences: int,
     *   manuallyRejected: int,
     *   reusedStoredAi: int,
     *   aiChecks: int,
     *   protectedHistory: int,
     *   kept: int
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

            return [
                'busy' => true,
                'scanned' => 0,
                'deletedOffers' => 0,
                'deletedApplications' => 0,
                'deletedOccurrences' => 0,
                'manuallyRejected' => 0,
                'reusedStoredAi' => 0,
                'aiChecks' => 0,
                'protectedHistory' => 0,
                'kept' => 0,
            ];
        }

        try {
            $settings = $this->data->settings();
            $offers = $this->em->getRepository(JobOffer::class)->findAll();
            $applicationRepository = $this->em->getRepository(Application::class);
            $occurrenceRepository = $this->em->getRepository(JobSourceOccurrence::class);

            $deletedOffers = 0;
            $deletedApplications = 0;
            $deletedOccurrences = 0;
            $manuallyRejected = 0;
            $reusedStoredAi = 0;
            $protectedHistory = 0;
            $kept = 0;

            foreach ($offers as $offer) {
                if (!$offer instanceof JobOffer) {
                    continue;
                }

                $applications = array_values(array_filter(
                    $applicationRepository->findBy(['jobOffer' => $offer]),
                    static fn (mixed $application): bool => $application instanceof Application,
                ));

                if ($this->hasProtectedApplicationHistory($applications)) {
                    ++$protectedHistory;
                    ++$kept;
                    continue;
                }

                $manualNoMatch = $this->hasManualNoMatch($applications);
                $storedAiNoMatch = !$manualNoMatch && $this->hasReusableStoredAiRejection($offer, $settings);

                if (!$manualNoMatch && !$storedAiNoMatch) {
                    ++$kept;
                    continue;
                }

                if ($manualNoMatch) {
                    ++$manuallyRejected;
                } else {
                    ++$reusedStoredAi;
                }

                $occurrences = array_values(array_filter(
                    $occurrenceRepository->findBy(['jobOffer' => $offer]),
                    static fn (mixed $occurrence): bool => $occurrence instanceof JobSourceOccurrence,
                ));

                foreach ($applications as $application) {
                    $this->em->remove($application);
                    ++$deletedApplications;
                }
                foreach ($occurrences as $occurrence) {
                    $this->em->remove($occurrence);
                    ++$deletedOccurrences;
                }
                $this->em->remove($offer);
                ++$deletedOffers;
            }

            $this->em->flush();
            $this->em->clear();

            return [
                'busy' => false,
                'scanned' => count($offers),
                'deletedOffers' => $deletedOffers,
                'deletedApplications' => $deletedApplications,
                'deletedOccurrences' => $deletedOccurrences,
                'manuallyRejected' => $manuallyRejected,
                'reusedStoredAi' => $reusedStoredAi,
                // Kept for API compatibility. Cleanup no longer performs fresh AI calls.
                'aiChecks' => 0,
                'protectedHistory' => $protectedHistory,
                'kept' => $kept,
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param list<Application> $applications */
    private function hasProtectedApplicationHistory(array $applications): bool
    {
        foreach ($applications as $application) {
            if (!in_array($application->getStatus(), self::DELETABLE_APPLICATION_STATUSES, true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<Application> $applications */
    private function hasManualNoMatch(array $applications): bool
    {
        foreach ($applications as $application) {
            if ($application->getStatus() === 'IGNORED_NOT_MATCH') {
                return true;
            }
        }

        return false;
    }

    private function hasReusableStoredAiRejection(JobOffer $offer, UserSettings $settings): bool
    {
        $offerData = $offer->toArray();
        $reasons = is_array($offerData['scoreReasons'] ?? null) ? $offerData['scoreReasons'] : [];
        $decisionIsNoMatch = false;
        $confidence = null;
        $hasEvidence = $offer->getScore() < $settings->getMatchingThreshold();

        foreach ($reasons as $reason) {
            if (!is_string($reason)) {
                continue;
            }

            if (preg_match('/^Analyse IA\s*:\s*NO_MATCH\s*[·-]\s*confiance\s+(\d{1,3})%/iu', $reason, $matches) === 1) {
                $decisionIsNoMatch = true;
                $confidence = max(0, min(100, (int) $matches[1]));
                continue;
            }

            if (str_starts_with($reason, 'Prérequis principaux manquants : ')
                || str_starts_with($reason, 'Conflits détectés : ')) {
                $hasEvidence = true;
            }
        }

        return $decisionIsNoMatch
            && $confidence !== null
            && $confidence >= (int) round(AiOfferIntakeFilter::MINIMUM_REJECTION_CONFIDENCE * 100)
            && $hasEvidence;
    }

    private function ensurePrivateDirectory(): void
    {
        if (!is_dir($this->privateDir) && !mkdir($this->privateDir, 0700, true) && !is_dir($this->privateDir)) {
            throw new \RuntimeException('Impossible de créer le dossier privé JobPilot.');
        }
    }
}
