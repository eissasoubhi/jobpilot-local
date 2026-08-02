<?php

namespace App\Service;

use App\Entity\UserSettings;

final class SalaryExpectationCalculator
{
    /**
     * @return array{eligible: bool, proposed: ?int, reason: ?string}
     */
    public function calculate(string $contractType, ?int $minimum, ?int $maximum, UserSettings $settings): array
    {
        $contract = mb_strtolower($contractType);
        if (!str_contains($contract, 'cdi')) {
            return ['eligible' => true, 'proposed' => null, 'reason' => null];
        }

        $advertisedMaximum = $maximum ?? $minimum;
        if ($advertisedMaximum !== null && $advertisedMaximum < $settings->getMinimumCdiSalary()) {
            return [
                'eligible' => false,
                'proposed' => null,
                'reason' => sprintf('Rémunération maximale inférieure au minimum CDI de %d €.', $settings->getMinimumCdiSalary()),
            ];
        }

        if ($minimum === null && $maximum === null) {
            return ['eligible' => true, 'proposed' => null, 'reason' => null];
        }

        if ($maximum === null) {
            return ['eligible' => true, 'proposed' => $minimum, 'reason' => null];
        }

        // Jusqu'à 50 k€, viser le maximum annoncé. Au-delà, rester environ 5 k€ sous le maximum,
        // sans descendre sous le minimum de la fourchette.
        $proposed = $maximum <= 50000 ? $maximum : max($minimum ?? 0, $maximum - 5000);

        return ['eligible' => true, 'proposed' => $proposed, 'reason' => null];
    }
}
