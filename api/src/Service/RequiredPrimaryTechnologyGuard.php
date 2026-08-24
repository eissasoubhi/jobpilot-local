<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;

final class RequiredPrimaryTechnologyGuard
{
    private const SCORE_CAP = 45;

    /**
     * Role-defining languages and frameworks may make an offer ineligible when
     * they are explicitly mandatory. Infrastructure, databases and tooling are
     * deliberately excluded from this hard guard because they are usually softer
     * gaps that should affect ranking rather than reject an otherwise valid role.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_DEFINING_TECHNOLOGIES = [
        'PHP' => ['php'],
        'Symfony' => ['symfony'],
        'Laravel' => ['laravel'],
        'Java' => ['java'],
        'Spring Boot' => ['spring boot', 'spring framework', 'spring'],
        'Python' => ['python'],
        'Django' => ['django'],
        'FastAPI' => ['fastapi'],
        'Flask' => ['flask'],
        '.NET / C#' => ['asp.net', 'dotnet', '.net', 'c#'],
        'Node.js' => ['node.js', 'nodejs'],
        'NestJS' => ['nestjs', 'nest.js'],
        'Express.js' => ['express.js', 'expressjs'],
        'Go' => ['golang', 'go developer', 'go engineer', 'développeur go', 'developpeur go'],
        'Ruby' => ['ruby'],
        'Ruby on Rails' => ['ruby on rails', 'rails'],
        'Rust' => ['rust'],
        'Kotlin' => ['kotlin'],
        'Scala' => ['scala'],
        'C++' => ['c++', 'cpp'],
        'React' => ['react', 'react.js', 'reactjs'],
        'Next.js' => ['next.js', 'nextjs', 'next js'],
        'Vue.js' => ['vue.js', 'vuejs', 'vue 3', 'vue3'],
        'Nuxt' => ['nuxt', 'nuxt.js', 'nuxtjs'],
        'Angular' => ['angular', 'angularjs'],
        'TypeScript' => ['typescript'],
        'JavaScript' => ['javascript'],
        'Drupal' => ['drupal'],
        'WordPress' => ['wordpress'],
    ];

    public function __construct(private readonly JobProfileTechnologyComparisonService $comparison)
    {
    }

    /**
     * @return array{hardRejected: bool, scoreCap: ?int, reasons: list<string>}
     */
    public function evaluate(JobOffer $job, UserSettings $settings): array
    {
        $comparison = $this->comparison->compare($job, $settings);
        $candidateText = implode("\n", [...$settings->getTargetJobs(), ...$settings->getSkills()]);
        $candidateTechnologies = $this->detectRoleTechnologies($candidateText);

        $missingMustHaves = [];
        foreach ($comparison['missingMustHaves'] as $technology) {
            $canonical = $this->canonicalTechnology($technology);
            if ($canonical !== null && !in_array($canonical, $candidateTechnologies, true)) {
                $missingMustHaves[] = $canonical;
            }
        }

        // A role-defining technology named in the job title is primary evidence.
        // Separators such as "/" or "&" are treated as stack composition, not as
        // an alternative. Only explicit language such as "Java or PHP" can waive
        // one missing technology, and that waiver is scoped to the technology in
        // the same alternative expression.
        foreach ($this->detectRoleTechnologies($job->getTitle()) as $technology) {
            if (!in_array($technology, $candidateTechnologies, true)) {
                $missingMustHaves[] = $technology;
            }
        }

        foreach ($this->detectStrongRequiredTechnologiesFromDescription($job->getDescription()) as $technology) {
            if (!in_array($technology, $candidateTechnologies, true)) {
                $missingMustHaves[] = $technology;
            }
        }

        // Reuse explicit AI must-have evidence when it has already been persisted.
        foreach ($job->getScoreReasons() as $reason) {
            if (!is_string($reason) || !str_starts_with($reason, 'Prérequis principaux manquants : ')) {
                continue;
            }

            foreach ($this->detectRoleTechnologies($reason) as $technology) {
                if (!in_array($technology, $candidateTechnologies, true)) {
                    $missingMustHaves[] = $technology;
                }
            }
        }

        $missingMustHaves = array_values(array_unique($missingMustHaves));
        $blockingMissing = array_values(array_filter(
            $missingMustHaves,
            fn (string $technology): bool => !$this->technologyCoveredByExplicitAlternative(
                $job,
                $technology,
                $candidateTechnologies,
            ),
        ));

        if ($blockingMissing === []) {
            return ['hardRejected' => false, 'scoreCap' => null, 'reasons' => []];
        }

        $reasons = array_map(
            static fn (string $technology): string => 'Technologie principale obligatoire manquante : '.$technology.'.',
            $blockingMissing,
        );
        $reasons[] = 'Une technologie de stack principale obligatoire absente du profil ne peut pas être compensée par les autres correspondances.';

        return [
            'hardRejected' => true,
            'scoreCap' => self::SCORE_CAP,
            'reasons' => $reasons,
        ];
    }

    /**
     * An explicit alternative only waives the missing technology that appears in
     * the same alternative expression. Example: "backend Java required; frontend
     * Angular or React" must still reject Java for a React candidate.
     *
     * @param list<string> $candidateTechnologies
     */
    private function technologyCoveredByExplicitAlternative(
        JobOffer $job,
        string $missingTechnology,
        array $candidateTechnologies,
    ): bool {
        $text = $job->getTitle()."\n".mb_substr($job->getDescription(), 0, 2200);
        $segments = preg_split('/(?<=[.!?;])\s+|\R+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($segments as $segment) {
            if (preg_match('/\b(?:or|ou|either)\b|au choix|one of|l[’\']un ou l[’\']autre|l[’\']une ou l[’\']autre/iu', $segment) !== 1) {
                continue;
            }

            $technologies = $this->detectRoleTechnologies($segment);
            if (!in_array($missingTechnology, $technologies, true) || count($technologies) < 2) {
                continue;
            }

            foreach ($technologies as $technology) {
                if ($technology !== $missingTechnology && in_array($technology, $candidateTechnologies, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<string> */
    private function detectStrongRequiredTechnologiesFromDescription(string $description): array
    {
        $segments = preg_split(
            '/(?<=[.!?;])\s+|\R+|\s+-\s+/u',
            mb_substr($description, 0, 3200),
            -1,
            PREG_SPLIT_NO_EMPTY,
        ) ?: [];
        $result = [];

        foreach ($segments as $segment) {
            $technologies = $this->detectRoleTechnologies($segment);
            if ($technologies === []) {
                continue;
            }

            // Explicitly soft/secondary/legacy wording must never become a hard
            // rejection just because the same sentence also contains a role word.
            if (preg_match(
                '/nice[- ]?to[- ]?have|optional|optionnel|facultatif|bonus|un plus|atout|appr[eé]ci[eé]e?s?|souhait[eé]e?s?|legacy|ancien(?:ne)?|migration|familiarit[eé]|exposure|not required|non requis|connaissance.*(?:plus|appr[eé]ci[eé]e)/iu',
                $segment,
            ) === 1) {
                continue;
            }

            $explicitRequirementCue = preg_match(
                '/\b(?:required|mandatory|must|requires?|requis(?:e|es|s)?|obligatoire(?:s)?|indispensable(?:s)?|exig[eé]e?s?|ma[iî]tris(?:e|er|ez)|expertise)\b/iu',
                $segment,
            ) === 1;
            $roleStructureCue = preg_match(
                '/\b(?:stack|environnement technique|backend|front(?:end)?|c[oô]t[eé]\s+(?:back|front)|d[eé]veloppeur|developpeur|developer|engineer|ing[eé]nieur)\b/iu',
                $segment,
            ) === 1;

            if (!$explicitRequirementCue && !$roleStructureCue) {
                continue;
            }

            foreach ($technologies as $technology) {
                $result[] = $technology;
            }
        }

        return array_values(array_unique($result));
    }

    /** @return list<string> */
    private function detectRoleTechnologies(string $text): array
    {
        $result = [];

        foreach (self::ROLE_DEFINING_TECHNOLOGIES as $technology => $aliases) {
            foreach ($aliases as $alias) {
                if ($this->containsTerm($text, $alias)) {
                    $result[] = $technology;
                    break;
                }
            }
        }

        return $result;
    }

    private function canonicalTechnology(string $value): ?string
    {
        foreach (self::ROLE_DEFINING_TECHNOLOGIES as $technology => $aliases) {
            if (mb_strtolower(trim($value)) === mb_strtolower($technology)) {
                return $technology;
            }

            foreach ($aliases as $alias) {
                if (mb_strtolower(trim($value)) === mb_strtolower($alias)) {
                    return $technology;
                }
            }
        }

        return null;
    }

    private function containsTerm(string $text, string $term): bool
    {
        return preg_match(
            '/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/iu',
            $text,
        ) === 1;
    }
}
