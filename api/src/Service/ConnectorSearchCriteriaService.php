<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ConnectorSyncRun;
use App\Entity\SourceConnector;
use App\JobDiscovery\Application\ConnectorRegistry;
use Doctrine\ORM\EntityManagerInterface;

final class ConnectorSearchCriteriaService
{
    private const MAX_ITEMS = 20;
    private const MAX_LENGTH = 120;
    private const MAX_EFFECTIVE_QUERIES = 6;

    public function __construct(
        private LocalDataService $data,
        private EntityManagerInterface $entityManager,
        private ConnectorRegistry $registry,
    ) {
    }

    /** @return array<string, mixed> */
    public function get(string $code): array
    {
        $connector = $this->registry->get($code);
        $normalizedCode = strtolower(trim($connector->code()));
        if ($normalizedCode !== 'france-travail') {
            throw new \InvalidArgumentException('Ce connecteur ne propose pas encore de critères modifiables.');
        }

        $settings = $this->data->settings();

        return $this->response(
            $connector->name(),
            $normalizedCode,
            $settings->getTargetJobs(),
            $settings->getSkills(),
        );
    }

    /**
     * @param mixed $targetJobs
     * @param mixed $skills
     *
     * @return array<string, mixed>
     */
    public function update(string $code, mixed $targetJobs, mixed $skills): array
    {
        $current = $this->get($code);
        $normalizedTargetJobs = $this->normalizeList($targetJobs, 'targetJobs');
        $normalizedSkills = $this->normalizeList($skills, 'skills');
        $queries = $this->effectiveQueries($normalizedTargetJobs, $normalizedSkills);

        if ($queries === []) {
            throw new \InvalidArgumentException('Ajoute au moins un intitulé ciblé ou une compétence exploitable.');
        }

        $settings = $this->data->settings()->fill([
            'targetJobs' => $normalizedTargetJobs,
            'skills' => $normalizedSkills,
        ]);
        $this->entityManager->flush();

        return $this->response(
            (string) $current['name'],
            (string) $current['code'],
            $settings->getTargetJobs(),
            $settings->getSkills(),
        );
    }

    /**
     * @param list<string> $targetJobs
     * @param list<string> $skills
     *
     * @return array<string, mixed>
     */
    private function response(string $name, string $code, array $targetJobs, array $skills): array
    {
        $effectiveQueries = $this->effectiveQueries($targetJobs, $skills);
        $latestSearchDiagnostics = $this->latestSearchDiagnostics($code);
        if ($latestSearchDiagnostics !== null) {
            $diagnosticQueries = array_values(array_map(
                static fn (mixed $query): string => is_array($query)
                    ? trim((string) ($query['query'] ?? ''))
                    : '',
                is_array($latestSearchDiagnostics['queries'] ?? null)
                    ? $latestSearchDiagnostics['queries']
                    : [],
            ));
            $latestSearchDiagnostics['matchesCurrentCriteria'] = $diagnosticQueries === $effectiveQueries;
        }

        return [
            'code' => $code,
            'name' => $name,
            'scope' => 'GLOBAL',
            'targetJobs' => $targetJobs,
            'skills' => $skills,
            'effectiveQueries' => $effectiveQueries,
            'latestSearchDiagnostics' => $latestSearchDiagnostics,
            'fixedCriteria' => [
                ['key' => 'sort', 'label' => 'Tri', 'value' => 'Offres les plus récentes'],
                ['key' => 'limit', 'label' => 'Limite', 'value' => '6 requêtes maximum par synchronisation'],
            ],
            'limits' => [
                'maxItemsPerList' => self::MAX_ITEMS,
                'maxItemLength' => self::MAX_LENGTH,
                'maxEffectiveQueries' => self::MAX_EFFECTIVE_QUERIES,
            ],
            'note' => 'Ces intitulés et compétences sont les critères globaux de JobPilot. Les modifier ici met aussi à jour les réglages utilisés par les autres connecteurs compatibles.',
        ];
    }

    /** @return array<string, mixed>|null */
    private function latestSearchDiagnostics(string $code): ?array
    {
        $connector = $this->entityManager->getRepository(SourceConnector::class)->findOneBy(['code' => $code]);
        if (!$connector instanceof SourceConnector) {
            return null;
        }

        $run = $this->entityManager->getRepository(ConnectorSyncRun::class)->findOneBy(
            ['connector' => $connector],
            ['startedAt' => 'DESC'],
        );
        if (!$run instanceof ConnectorSyncRun) {
            return null;
        }

        $serialized = $run->toArray();
        $details = is_array($serialized['details'] ?? null) ? $serialized['details'] : [];
        $diagnostics = is_array($details['searchDiagnostics'] ?? null)
            ? $details['searchDiagnostics']
            : null;
        if ($diagnostics === null) {
            return null;
        }

        return [
            'startedAt' => (string) ($serialized['startedAt'] ?? ''),
            ...$diagnostics,
        ];
    }

    /** @return list<string> */
    private function normalizeList(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf('Le champ %s doit être une liste.', $field));
        }
        if (count($value) > self::MAX_ITEMS) {
            throw new \InvalidArgumentException(sprintf('Le champ %s accepte au maximum %d éléments.', $field, self::MAX_ITEMS));
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_scalar($item) && $item !== null) {
                throw new \InvalidArgumentException(sprintf('Le champ %s contient une valeur invalide.', $field));
            }

            $item = trim(preg_replace('/\s+/u', ' ', (string) $item) ?? '');
            if ($item === '') {
                continue;
            }
            if (mb_strlen($item) > self::MAX_LENGTH) {
                throw new \InvalidArgumentException(sprintf(
                    'Chaque élément de %s doit contenir au maximum %d caractères.',
                    $field,
                    self::MAX_LENGTH,
                ));
            }

            $key = mb_strtolower($item);
            if (!isset($normalized[$key])) {
                $normalized[$key] = $item;
            }
        }

        return array_values($normalized);
    }

    /**
     * Mirrors the current France Travail provider query construction so the
     * interface shows exactly what is sent in the motsCles parameter.
     *
     * @param list<string> $targetJobs
     * @param list<string> $skills
     *
     * @return list<string>
     */
    private function effectiveQueries(array $targetJobs, array $skills): array
    {
        $queries = [];
        foreach ($targetJobs as $targetJob) {
            $query = preg_replace('/\b(senior|developer|developpeur|développeur|engineer|native)\b/iu', ' ', $targetJob) ?? '';
            $query = trim(preg_replace('/[^\pL\pN.+#]+/u', ' ', $query) ?? '');
            if (mb_strlen($query) >= 3) {
                $key = mb_strtolower($query);
                if (!isset($queries[$key])) {
                    $queries[$key] = $query;
                }
            }
        }

        if ($queries === []) {
            foreach ($skills as $skill) {
                $skill = trim($skill);
                if (mb_strlen($skill) >= 3) {
                    $key = mb_strtolower($skill);
                    if (!isset($queries[$key])) {
                        $queries[$key] = $skill;
                    }
                }
            }
        }

        return array_slice(array_values($queries), 0, self::MAX_EFFECTIVE_QUERIES);
    }
}
