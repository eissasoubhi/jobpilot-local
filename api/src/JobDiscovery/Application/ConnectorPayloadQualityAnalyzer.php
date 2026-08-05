<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

final class ConnectorPayloadQualityAnalyzer
{
    /** @var list<string> */
    private const REQUIRED_FIELDS = ['externalId', 'title', 'description'];

    /** @var list<string> */
    private const RECOMMENDED_FIELDS = ['company', 'sourceUrl', 'location', 'contractType', 'publishedAt'];

    /**
     * @param list<array<string, mixed>> $payloads
     * @return array{
     *   received: int,
     *   requiredCompleteness: float|null,
     *   recommendedCompleteness: float|null,
     *   overallCompleteness: float|null,
     *   missingRequiredRecords: int,
     *   fields: array<string, array{category: string, present: int, missing: int, rate: float|null}>,
     *   warnings: list<string>
     * }
     */
    public function analyze(array $payloads): array
    {
        $received = count($payloads);
        $fields = [];
        $missingRequiredRecords = 0;

        foreach ([
            'required' => self::REQUIRED_FIELDS,
            'recommended' => self::RECOMMENDED_FIELDS,
        ] as $category => $names) {
            foreach ($names as $name) {
                $present = 0;
                foreach ($payloads as $payload) {
                    if ($this->isPresent($payload[$name] ?? null)) {
                        ++$present;
                    }
                }

                $fields[$name] = [
                    'category' => $category,
                    'present' => $present,
                    'missing' => max(0, $received - $present),
                    'rate' => $received > 0 ? round($present * 100 / $received, 1) : null,
                ];
            }
        }

        foreach ($payloads as $payload) {
            foreach (self::REQUIRED_FIELDS as $field) {
                if (!$this->isPresent($payload[$field] ?? null)) {
                    ++$missingRequiredRecords;
                    break;
                }
            }
        }

        $requiredCompleteness = $this->categoryCompleteness($fields, 'required', $received);
        $recommendedCompleteness = $this->categoryCompleteness($fields, 'recommended', $received);
        $requiredSlots = count(self::REQUIRED_FIELDS) * 2;
        $recommendedSlots = count(self::RECOMMENDED_FIELDS);
        $overallCompleteness = $received > 0
            ? round((($requiredCompleteness ?? 0.0) * $requiredSlots + ($recommendedCompleteness ?? 0.0) * $recommendedSlots) / ($requiredSlots + $recommendedSlots), 1)
            : null;

        $warnings = [];
        if ($missingRequiredRecords > 0) {
            $warnings[] = sprintf(
                '%d offre(s) sur %d ont au moins un champ obligatoire manquant.',
                $missingRequiredRecords,
                $received,
            );
        }
        foreach ($fields as $field => $metrics) {
            if ($metrics['category'] === 'required' && $metrics['missing'] > 0) {
                $warnings[] = sprintf(
                    'Champ obligatoire « %s » absent de %d offre(s).',
                    $field,
                    $metrics['missing'],
                );
            }
        }
        if ($received > 0 && $recommendedCompleteness !== null && $recommendedCompleteness < 60.0) {
            $warnings[] = sprintf(
                'La complétude des champs recommandés est faible : %.1f %%.',
                $recommendedCompleteness,
            );
        }

        return [
            'received' => $received,
            'requiredCompleteness' => $requiredCompleteness,
            'recommendedCompleteness' => $recommendedCompleteness,
            'overallCompleteness' => $overallCompleteness,
            'missingRequiredRecords' => $missingRequiredRecords,
            'fields' => $fields,
            'warnings' => array_slice($warnings, 0, 8),
        ];
    }

    /**
     * @param array<string, array{category: string, present: int, missing: int, rate: float|null}> $fields
     */
    private function categoryCompleteness(array $fields, string $category, int $received): ?float
    {
        if ($received === 0) {
            return null;
        }

        $selected = array_filter(
            $fields,
            static fn (array $metrics): bool => $metrics['category'] === $category,
        );
        if ($selected === []) {
            return null;
        }

        $present = array_sum(array_column($selected, 'present'));
        $slots = count($selected) * $received;

        return round($present * 100 / $slots, 1);
    }

    private function isPresent(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }
}
