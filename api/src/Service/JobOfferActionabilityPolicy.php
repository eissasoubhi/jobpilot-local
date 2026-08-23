<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JobOffer;

final class JobOfferActionabilityPolicy
{
    public const REVIEW_WINDOW_DAYS = 30;

    public function isWithinReviewWindow(JobOffer $job, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();
        $reference = $job->getPublishedAt() ?? $job->getDiscoveredAt();
        $cutoff = $now->sub(new \DateInterval('P'.self::REVIEW_WINDOW_DAYS.'D'));

        return $reference >= $cutoff;
    }
}
