<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JobOffer;

final class JobRankingOrderService
{
    public function compare(JobOffer $aJob, int $aPriority, JobOffer $bJob, int $bPriority): int
    {
        $priorityOrder = $bPriority <=> $aPriority;
        if ($priorityOrder !== 0) {
            return $priorityOrder;
        }

        $matchOrder = $bJob->getScore() <=> $aJob->getScore();
        if ($matchOrder !== 0) {
            return $matchOrder;
        }

        $publishedOrder = ($bJob->getPublishedAt()?->getTimestamp() ?? 0)
            <=> ($aJob->getPublishedAt()?->getTimestamp() ?? 0);
        if ($publishedOrder !== 0) {
            return $publishedOrder;
        }

        // Persisted offers always have an id. Ascending id is intentionally only a
        // deterministic fallback: it must not add an implicit recency signal after
        // priority, match quality and publication date have already tied.
        return ($aJob->getId() ?? PHP_INT_MAX) <=> ($bJob->getId() ?? PHP_INT_MAX);
    }
}
