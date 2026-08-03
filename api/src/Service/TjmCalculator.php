<?php

declare(strict_types=1);

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
        $this->validateRange($minimum, $maximum);

        if ($fixed !== null) {
            return $this->withinConfiguredLimits($fixed, $settings);
        }

        if ($minimum !== null && $maximum !== null) {
            if ($maximum < $settings->getMinimumFreelanceTjm()) {
                return null;
            }

            if ($maximum <= 500) {
                return min($maximum, $settings->getMaximumTjm());
            }

            $midpoint = (int) (round((($minimum + $maximum) / 2) / 5) * 5);

            return min($midpoint, $settings->getMaximumTjm());
        }

        if (!$answerRequired) {
            return null;
        }

        // Full remote follows the same geographical rule as any other mission.
        // The dedicated remote value is only a fallback when no location is known.
        if ($this->isIleDeFrance($location)) {
            return $this->withinConfiguredLimits($settings->getDefaultIdfTjm(), $settings);
        }

        if (trim($location) !== '') {
            return $this->withinConfiguredLimits($settings->getDefaultOutsideIdfTjm(), $settings);
        }

        $fallback = $this->isFullyRemote($workMode)
            ? $settings->getDefaultRemoteTjm()
            : $settings->getDefaultOutsideIdfTjm();

        return $this->withinConfiguredLimits($fallback, $settings);
    }

    private function validateRange(?int $minimum, ?int $maximum): void
    {
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw new \InvalidArgumentException('Le TJM minimum ne peut pas dépasser le TJM maximum.');
        }
    }

    private function withinConfiguredLimits(int $amount, UserSettings $settings): ?int
    {
        if ($amount < $settings->getMinimumFreelanceTjm()) {
            return null;
        }

        return min($amount, $settings->getMaximumTjm());
    }

    private function isFullyRemote(string $workMode): bool
    {
        $value = mb_strtolower($workMode);

        return str_contains($value, 'full remote')
            || str_contains($value, '100% remote')
            || str_contains($value, '100 % remote')
            || str_contains($value, 'télétravail complet')
            || str_contains($value, 'fully remote');
    }

    private function isIleDeFrance(string $location): bool
    {
        $value = mb_strtolower($location);

        foreach (['paris', 'île-de-france', 'ile-de-france', 'idf'] as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return preg_match('/(?:^|\D)(75|77|78|91|92|93|94|95)(?:\D|$)/', $value) === 1;
    }
}
