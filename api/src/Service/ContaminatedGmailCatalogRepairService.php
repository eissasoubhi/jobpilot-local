<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;

final class ContaminatedGmailCatalogRepairService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JobDescriptionContaminationDetector $detector,
        private JobProcessor $processor,
        private LocalDataService $data,
    ) {
    }

    /** @return array{scanned: int, contaminated: int, repaired: int, reprocessed: int, remainingPossible: bool} */
    public function repair(int $maxRepairs = 50): array
    {
        $maxRepairs = max(1, min(200, $maxRepairs));
        $jobs = $this->em->getRepository(JobOffer::class)->findBy([], ['discoveredAt' => 'DESC'], 2_000);
        $settings = $this->data->settings();
        $profile = $this->data->profile();
        $scanned = 0;
        $contaminated = 0;
        $repaired = 0;
        $reprocessed = 0;
        $remainingPossible = false;

        foreach ($jobs as $job) {
            if (!$job instanceof JobOffer) {
                continue;
            }
            ++$scanned;

            if (!$this->isGmailOffer($job) || !$this->detector->isMultiOfferDigest($job->getDescription())) {
                continue;
            }
            ++$contaminated;

            if ($repaired >= $maxRepairs) {
                $remainingPossible = true;
                continue;
            }

            $summary = $this->detector->localSummary($job->getDescription(), $job->getTitle());
            if ($summary === '' || $summary === trim($job->getDescription())) {
                continue;
            }

            $job->fill(['description' => $summary]);
            ++$repaired;

            if ($this->canReprocessAutomatically($job)) {
                $this->processor->process($job, $settings, $profile);
                ++$reprocessed;
            } else {
                $this->em->persist($job);
            }
        }

        $this->em->flush();

        return [
            'scanned' => $scanned,
            'contaminated' => $contaminated,
            'repaired' => $repaired,
            'reprocessed' => $reprocessed,
            'remainingPossible' => $remainingPossible,
        ];
    }

    private function isGmailOffer(JobOffer $job): bool
    {
        if (strtolower((string) $job->getSourceCode()) === 'gmail') {
            return true;
        }

        foreach ($job->getOccurrences() as $occurrence) {
            if (strtolower($occurrence->getSourceCode()) === 'gmail') {
                return true;
            }
        }

        return false;
    }

    private function canReprocessAutomatically(JobOffer $job): bool
    {
        return in_array($job->getStatus(), ['DISCOVERED', 'PREPARED', 'REJECTED_BY_FILTER'], true);
    }
}
