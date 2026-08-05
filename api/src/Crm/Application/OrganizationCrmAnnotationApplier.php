<?php

declare(strict_types=1);

namespace App\Crm\Application;

use App\Entity\CrmOrganizationAnnotation;

final class OrganizationCrmAnnotationApplier
{
    /**
     * @param array{
     *   generatedAt: string,
     *   organizationCount: int,
     *   contactCount: int,
     *   organizations: list<array<string, mixed>>
     * } $directory
     * @param iterable<CrmOrganizationAnnotation> $annotations
     *
     * @return array<string, mixed>
     */
    public function apply(array $directory, iterable $annotations): array
    {
        /** @var array<string, array<string, mixed>> $annotationsByKey */
        $annotationsByKey = [];
        foreach ($annotations as $annotation) {
            if (!$annotation instanceof CrmOrganizationAnnotation || $annotation->isEmpty()) {
                continue;
            }

            $annotationsByKey[$annotation->getOrganizationKey()] = $annotation->toArray();
        }

        $appliedCount = 0;
        foreach ($directory['organizations'] as &$organization) {
            $organizationKey = (string) ($organization['key'] ?? '');
            $sourceName = (string) ($organization['name'] ?? '');
            $annotation = $annotationsByKey[$organizationKey] ?? null;

            $organization['sourceName'] = $sourceName;
            $organization['annotation'] = null;

            if (!is_array($annotation)) {
                continue;
            }

            $displayName = is_string($annotation['displayName'] ?? null)
                ? trim($annotation['displayName'])
                : '';
            $note = is_string($annotation['note'] ?? null)
                ? $annotation['note']
                : null;

            $organization['name'] = $displayName !== '' ? $displayName : $sourceName;
            $organization['annotation'] = [
                'displayName' => $displayName !== '' ? $displayName : null,
                'note' => $note,
                'updatedAt' => $annotation['updatedAt'] ?? null,
            ];
            ++$appliedCount;
        }
        unset($organization);

        usort($directory['organizations'], static function (array $left, array $right): int {
            $activityComparison = strcmp(
                (string) ($right['lastActivityAt'] ?? ''),
                (string) ($left['lastActivityAt'] ?? ''),
            );

            return $activityComparison !== 0
                ? $activityComparison
                : strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        $directory['annotationCount'] = $appliedCount;

        return $directory;
    }
}
