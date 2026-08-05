<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

final class ConnectorPayloadQualityAnalyzer
{
    /** @var list<string> */
    private const DEFAULT_REQUIRED_FIELDS = ['externalId', 'title', 'description'];

    /** @var list<string> */
    private const DEFAULT_RECOMMENDED_FIELDS = ['company', 'sourceUrl', 'location', 'contractType', 'publishedAt'];

    public function __construct(private ?ConnectorQualityProfileRegistry $profiles = null)
    {
        $this->profiles ??= new ConnectorQualityProfileRegistry();
    }

    /**
     * @param list<array<string, mixed>> $payloads
     * @param list<string>|null $requiredFields
     * @param list<string>|null $recommendedFields
     * @return array{
     *   received: int,
     *   requiredCompleteness: float|null,
     *   recommendedCompleteness: float|null,
     *   overallCompleteness: float|null,
     *   missingRequiredRecords: int,
     *   fields: array<string, array{category: string, present: int, missing: int, rate: float|null}>,
     *   rules: array{profile: string, required: list<string>, recommended: list<string>},
     *   warnings: list<string>
     * }
     */
    public function analyze(
        array $payloads,
        ?array $requiredFields = null,
        ?array $recommendedFields = null,
    ): array {
        $source = trim((string) ($payloads[0]['source'] ?? ''));
        $profile = $requiredFields === null && $recommendedFields === null
            ? $this->profiles?->forSource($source)
            : null;
        $requiredFields = $this->normalizeFields(
            $requiredFields ?? $profile['required'] ?? self::DEFAULT_REQUIRED_FIELDS,
        );
        $recommendedFields = array_values(array_diff(
            $this->normalizeFields(
                $recommendedFields ?? $profile['recommended'] ?? self::DEFAULT_RECOMMENDED_FIELDS,
            ),
            $requiredFields,
        ));
        $received = count($payloads);
        $fields = [];
        $missingRequiredRecords = 0;

        foreach (['required' => $requiredFields, 'recommended' => $recommendedFields] as $category => $names) {
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
            foreach ($requiredFields as $field) {
                if (!$this->isPresent($payload[$field] ?? null)) {
                    ++$missingRequiredRecords;
                    break;
                }
            }
        }

        $requiredCompleteness = $this->categoryCompleteness($fields, 'required', $received);
        $recommendedCompleteness = $this->categoryCompleteness($fields, 'recommended', $received);
        $requiredSlots = count($requiredFields) * 2;
        $recommendedSlots = count($recommendedFields);
        $totalWeight = $requiredSlots + $recommendedSlots;
        $overallCompleteness = $received > 0 && $totalWeight > 0
            ? round((($requiredCompleteness ?? 100.0) * $requiredSlots + ($recommendedCompleteness ?? 100.0) * $recommendedSlots) / $totalWeight, 1)
            : null;

        $warnings = [];
        if ($missingRequiredRecords > 0) {
            $warnings[] = sprintf('%d offre(s) sur %d ont au moins un champ obligatoire manquant.', $missingRequiredRecords, $received);
        }
        foreach ($fields as $field => $metrics) {
            if ($metrics['category'] === 'required' && $metrics['missing'] > 0) {
                $warnings[] = sprintf('Champ obligatoire « %s » absent de %d offre(s).', $field, $metrics['missing']);
            }
        }
        if ($received > 0 && $recommendedCompleteness !== null && $recommendedCompleteness < 60.0) {
            $warnings[] = sprintf('La complétude des champs recommandés est faible : %.1f %%.', $recommendedCompleteness);
        }

        return [
            'received' => $received,
            'requiredCompleteness' => $requiredCompleteness,
            'recommendedCompleteness' => $recommendedCompleteness,
            'overallCompleteness' => $overallCompleteness,
            'missingRequiredRecords' => $missingRequiredRecords,
            'fields' => $fields,
            'rules' => [
                'profile' => $profile !== null ? mb_strtolower($source) : 'default',
                'required' => $requiredFields,
                'recommended' => $recommendedFields,
            ],
            'warnings' => array_slice($warnings, 0, 8),
        ];
    }

    /** @param list<string> $fields
     *  @return list<string>
     */
    private function normalizeFields(array $fields): array
    {
        $normalized = [];
        foreach ($fields as $field) {
            $field = trim($field);
            if ($field !== '' && !in_array($field, $normalized, true)) {
                $normalized[] = $field;
            }
        }

        return $normalized;
    }

    /** @param array<string, array{category: string, present: int, missing: int, rate: float|null}> $fields */
    private function categoryCompleteness(array $fields, string $category, int $received): ?float
    {
        if ($received === 0) {
            return null;
        }
        $selected = array_filter($fields, static fn (array $metrics): bool => $metrics['category'] === $category);
        if ($selected === []) {
            return null;
        }
        $present = array_sum(array_column($selected, 'present'));

        return round($present * 100 / (count($selected) * $received), 1);
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
