<?php

declare(strict_types=1);

namespace App\JobCatalog\Application;

final class ProfileFilteredJobOffer extends \RuntimeException
{
    public const REASON_SCORE_BELOW_THRESHOLD = 'score_below_threshold';
    public const REASON_MISSING_MUST_HAVE = 'missing_must_have';
    public const REASON_EXPLICIT_CONFLICT = 'explicit_conflict';

    /** @var list<string> */
    public const REASON_CODES = [
        self::REASON_SCORE_BELOW_THRESHOLD,
        self::REASON_MISSING_MUST_HAVE,
        self::REASON_EXPLICIT_CONFLICT,
    ];

    /** @var list<string> */
    public readonly array $reasonCodes;

    /** @param list<string> $reasonCodes */
    public function __construct(
        public readonly int $score,
        public readonly float $confidence,
        array $reasonCodes = [],
    ) {
        $this->reasonCodes = array_values(array_unique(array_filter(
            $reasonCodes,
            static fn (mixed $reasonCode): bool => is_string($reasonCode) && in_array($reasonCode, self::REASON_CODES, true),
        )));

        parent::__construct('Offre écartée avant enregistrement par le filtre IA de profil.');
    }
}
