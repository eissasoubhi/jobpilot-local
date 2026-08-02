<?php

namespace App\Service;

use App\Entity\UserSettings;

final class TjmCalculator
{
    public function calculate(
        ?int $fixed,
        ?int $minimum,
        ?int $maximum,
        string $location,
        string $workMode,
        UserSettings $settings,
        bool $answerRequired = true,
    ): ?int {
        if ($fixed !== null) {
            if ($fixed < $settings->getMinimumFreelanceTjm()) return null;
            return min($fixed, $settings->getMaximumTjm());
        }

        if ($minimum !== null && $maximum !== null) {
            if ($maximum < $settings->getMinimumFreelanceTjm()) return null;
            if ($maximum <= 500) return $maximum;
            $midpoint = (int) (round((($minimum + $maximum) / 2) / 5) * 5);
            return min($midpoint, $settings->getMaximumTjm());
        }

        if (!$answerRequired) return null;

        if ($this->isFullyRemote($workMode)) return $settings->getDefaultRemoteTjm();
        if ($this->isIleDeFrance($location)) return $settings->getDefaultIdfTjm();
        return $settings->getDefaultOutsideIdfTjm();
    }

    private function isFullyRemote(string $workMode): bool
    {
        $value = mb_strtolower($workMode);
        return str_contains($value, 'full remote') || str_contains($value, '100% remote') || str_contains($value, 'télétravail complet');
    }

    private function isIleDeFrance(string $location): bool
    {
        $value = mb_strtolower($location);
        foreach (['paris','île-de-france','ile-de-france','idf','92','93','94','95','78','77','91','75'] as $needle) {
            if (str_contains($value, $needle)) return true;
        }
        return false;
    }
}
