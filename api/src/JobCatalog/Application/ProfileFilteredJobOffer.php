<?php

declare(strict_types=1);

namespace App\JobCatalog\Application;

final class ProfileFilteredJobOffer extends \RuntimeException
{
    public function __construct(
        public readonly int $score,
        public readonly float $confidence,
    ) {
        parent::__construct('Offre écartée avant enregistrement par le filtre IA de profil.');
    }
}
