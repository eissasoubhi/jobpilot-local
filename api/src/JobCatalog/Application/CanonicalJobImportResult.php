<?php

declare(strict_types=1);

namespace App\JobCatalog\Application;

use App\Entity\JobOffer;
use App\Entity\JobSourceOccurrence;

final class CanonicalJobImportResult
{
    public const IMPORTED = 'IMPORTED';
    public const MERGED = 'MERGED';
    public const DUPLICATE = 'DUPLICATE';

    /** @param list<string> $matchReasons */
    public function __construct(
        private JobOffer $job,
        private JobSourceOccurrence $occurrence,
        private string $outcome,
        private string $matchType,
        private int $matchScore,
        private array $matchReasons = [],
    ) {
        if (!in_array($outcome, [self::IMPORTED, self::MERGED, self::DUPLICATE], true)) {
            throw new \InvalidArgumentException('Résultat d’import canonique invalide.');
        }
    }

    public function job(): JobOffer
    {
        return $this->job;
    }

    public function occurrence(): JobSourceOccurrence
    {
        return $this->occurrence;
    }

    public function outcome(): string
    {
        return $this->outcome;
    }

    public function matchType(): string
    {
        return $this->matchType;
    }

    public function matchScore(): int
    {
        return $this->matchScore;
    }

    /** @return list<string> */
    public function matchReasons(): array
    {
        return $this->matchReasons;
    }

    public function isImported(): bool
    {
        return $this->outcome === self::IMPORTED;
    }

    public function isMerged(): bool
    {
        return $this->outcome === self::MERGED;
    }

    public function isDuplicate(): bool
    {
        return $this->outcome === self::DUPLICATE;
    }
}
