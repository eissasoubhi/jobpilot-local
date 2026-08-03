<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\UserSettings;

final class SalaryExpectationCalculator
{
    /**
     * @return array{eligible: bool, proposed: ?int, reason: ?string}
     */
    public function calculate(string $contractType, ?int $minimum, ?int $maximum, UserSettings $settings): array
    {
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw new \InvalidArgumentException('Le salaire minimum ne peut pas dépasser le salaire maximum.');
        }

        if (!str_contains(mb_strtolower($contractType), 'cdi')) {
            return ['eligible' => true, 'proposed' => null, 'reason' => null];
        }

        $advertisedMaximum = $maximum ?? $minimum;
        if ($advertisedMaximum !== null && $advertisedMaximum < $settings->getMinimumCdiSalary()) {
            return [
                'eligible' => false,
                'proposed' => null,
                'reason' => sprintf(
                    'Rémunération maximale inférieure au minimum CDI de %d €.',
                    $settings->getMinimumCdiSalary(),
                ),
            ];
        }

        if ($minimum === null && $maximum === null) {
            return ['eligible' => true, 'proposed' => null, 'reason' => null];
        }

        if ($maximum === null) {
            return ['eligible' => true, 'proposed' => $minimum, 'reason' => null];
        }

        // Jusqu’à 50 k€ : viser le maximum annoncé.
        // Au-dessus : rester environ 5 k€ sous le maximum sans descendre sous le minimum.
        $proposed = $maximum <= 50_000
            ? $maximum
            : max($minimum ?? 0, $maximum - 5_000);

        return ['eligible' => true, 'proposed' => $proposed, 'reason' => null];
    }
}
