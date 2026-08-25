<?php

declare(strict_types=1);

namespace App\JobCatalog\Application;

final class ProfileFilteredJobOffer extends \RuntimeException
{
    /**
     * @param list<string> $missingMustHaves
     * @param list<string> $conflicts
     */
    public function __construct(
        public readonly int $score,
        public readonly float $confidence,
        public readonly array $missingMustHaves = [],
        public readonly array $conflicts = [],
    ) {
        parent::__construct('Offre écartée avant enregistrement par le filtre IA de profil.');
    }

    /** @return list<string> */
    public function reasons(): array
    {
        $reasons = [];
        if ($this->missingMustHaves !== []) {
            $reasons[] = 'Prérequis principaux manquants : '.implode(', ', $this->missingMustHaves);
        }
        if ($this->conflicts !== []) {
            $reasons[] = 'Conflits détectés : '.implode(' · ', $this->conflicts);
        }
        if ($reasons === []) {
            $reasons[] = sprintf(
                'Compatibilité IA insuffisante : score %d%% · confiance %d%%.',
                $this->score,
                (int) round($this->confidence * 100),
            );
        }

        return $reasons;
    }
}
