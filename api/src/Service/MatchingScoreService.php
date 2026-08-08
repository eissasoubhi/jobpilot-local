<?php

namespace App\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\Ai\AiJobMatchAnalyzerInterface;

final class MatchingScoreService
{
    private const PRIMARY_STACK_CONFLICT_CAP = 45;
    private const MIXED_REQUIRED_CAP = 60;
    private const STACK_RELATION_ALTERNATIVE = 'ALTERNATIVE';
    private const STACK_RELATION_MIXED_REQUIRED = 'MIXED_REQUIRED';

    /** @var array<string, list<string>> */
    private const BACKEND_STACK_PATTERNS = [
        'PHP/Symfony' => ['php', 'symfony', 'laravel'],
        'Java/Spring' => ['java', 'spring', 'spring boot', 'quarkus'],
        'Python' => ['python', 'django', 'fastapi', 'flask'],
        '.NET/C#' => ['.net', 'dotnet', 'c#', 'asp.net'],
        'Node.js/NestJS' => ['node.js', 'nodejs', 'nestjs', 'nest.js', 'express.js', 'expressjs'],
        'Ruby/Rails' => ['ruby', 'ruby on rails', 'rails'],
        'Go' => ['golang', 'go developer', 'go engineer', 'développeur go', 'developpeur go', 'ingénieur go', 'ingenieur go'],
        'Rust' => ['rust'],
        'Kotlin/Scala' => ['kotlin', 'scala'],
        'C/C++' => ['c++', 'cpp'],
    ];

    /**
     * Generic role/seniority words help describe a title but do not identify the
     * candidate's requested technology stack. They must never create a strong
     * title match on their own.
     *
     * @var list<string>
     */
    private const GENERIC_ROLE_TOKENS = [
        'developer',
        'developpeur',
        'développeur',
        'engineer',
        'ingenieur',
        'ingénieur',
        'backend',
        'back',
        'end',
        'frontend',
        'front',
        'fullstack',
        'full',
        'stack',
        'web',
        'api',
        'software',
        'senior',
        'lead',
        'tech',
        'expert',
        'experte',
        'confirme',
        'confirmé',
        'native',
    ];

    public function __construct(private readonly ?AiJobMatchAnalyzerInterface $aiAnalyzer = null)
    {
    }

    public function evaluate(JobOffer $job, UserSettings $settings): array
    {
        $text = mb_strtolower($job->getTitle().' '.$job->getDescription());
        $reasons = [];

        foreach ($settings->getExclusions() as $excluded) {
            if ($excluded !== '' && str_contains($text, mb_strtolower($excluded))) {
                return ['score' => 0, 'reasons' => ['Exclusion détectée : '.$excluded], 'hardRejected' => true];
            }
        }

        $aiAnalysis = $this->aiAnalyzer?->analyze($job, $settings);
        if ($aiAnalysis !== null) {
            $score = $aiAnalysis->score;
            $reasons = $aiAnalysis->toScoreReasons();

            if ($this->candidateTargetsPhpBackend($settings) && !$this->roleMatchesExplicitNonPhpTarget($aiAnalysis->primaryRole, $settings)) {
                if (in_array($aiAnalysis->phpRelevance, ['SECONDARY', 'CONTEXTUAL', 'ABSENT'], true)) {
                    $score = min($score, self::PRIMARY_STACK_CONFLICT_CAP);
                    $reasons[] = 'Profil principal non-PHP : score IA plafonné à '.self::PRIMARY_STACK_CONFLICT_CAP.'/100';
                } elseif ($aiAnalysis->phpRelevance === 'MIXED_REQUIRED') {
                    $score = min($score, self::MIXED_REQUIRED_CAP);
                    $reasons[] = 'PHP requis avec une autre stack principale : score IA plafonné à '.self::MIXED_REQUIRED_CAP.'/100';
                }
            }

            return [
                'score' => $score,
                'reasons' => $reasons,
                'hardRejected' => false,
            ];
        }

        $jobTitle = mb_strtolower($job->getTitle());
        $titleScore = 0;
        foreach ($settings->getTargetJobs() as $target) {
            $titleScore = max($titleScore, $this->titleCompatibilityScore($jobTitle, $target));
        }
        if ($titleScore > 0) $reasons[] = "Compatibilité intitulé : {$titleScore}/35";

        $skillMatches = [];
        foreach ($settings->getSkills() as $skill) {
            if (str_contains($text, mb_strtolower($skill))) $skillMatches[] = $skill;
        }
        $skillScore = min(35, count($skillMatches) * 5);
        if ($skillMatches !== []) $reasons[] = 'Compétences : '.implode(', ', array_slice($skillMatches, 0, 8));

        $seniorityScore = preg_match('/senior|lead|tech lead|confirmé|experte?|11\+?\s*ans/i', $text) ? 15 : 7;
        $contractScore = $this->contractScore($job->getContractType());
        $locationScore = 8;
        $freshnessScore = $this->freshnessScore($job->getPublishedAt());

        $score = min(100, $titleScore + $skillScore + $seniorityScore + $contractScore + $locationScore + $freshnessScore);
        $reasons[] = "Séniorité : {$seniorityScore}/15";
        $reasons[] = "Contrat : {$contractScore}/5";
        $reasons[] = "Fraîcheur : {$freshnessScore}/2";

        $stackRelation = $this->detectExplicitStackRelation($job);
        $primaryStacks = $stackRelation['stacks'] ?? $this->detectPrimaryBackendStacks($job);
        if ($primaryStacks !== []) {
            $reasons[] = 'Stack principale détectée : '.implode(' ou ', $primaryStacks);
            $reasons[] = in_array('PHP/Symfony', $primaryStacks, true)
                ? 'Profil PHP détecté : PHP fait partie des stacks principales'
                : 'Profil PHP détecté : non-PHP principal';

            if ($stackRelation !== null) {
                $reasons[] = $stackRelation['mode'] === self::STACK_RELATION_ALTERNATIVE
                    ? 'Relation des stacks détectée : alternatives explicites'
                    : 'Relation des stacks détectée : exigences cumulatives obligatoires';
            }

            if (
                $stackRelation !== null
                && $stackRelation['mode'] === self::STACK_RELATION_MIXED_REQUIRED
                && $this->candidateTargetsPhpBackend($settings)
                && in_array('PHP/Symfony', $primaryStacks, true)
                && count($primaryStacks) > 1
                && !$this->candidateExplicitlyTargetsAllStacks($settings, $primaryStacks)
            ) {
                $score = min($score, self::MIXED_REQUIRED_CAP);
                $reasons[] = 'PHP requis avec une autre stack principale : score plafonné à '.self::MIXED_REQUIRED_CAP.'/100';
            }

            $preferredStacks = $this->detectPreferredBackendStacks($settings);
            if (
                $titleScore < 25
                && $preferredStacks !== []
                && array_intersect($primaryStacks, $preferredStacks) === []
            ) {
                $score = min($score, self::PRIMARY_STACK_CONFLICT_CAP);
                $reasons[] = 'Conflit de stack principale avec le profil : score plafonné à '.self::PRIMARY_STACK_CONFLICT_CAP.'/100';
            }
        }

        return ['score' => $score, 'reasons' => $reasons, 'hardRejected' => false];
    }

    private function candidateTargetsPhpBackend(UserSettings $settings): bool
    {
        return in_array('PHP/Symfony', $this->detectPreferredBackendStacks($settings), true);
    }

    /** @param list<string> $stacks */
    private function candidateExplicitlyTargetsAllStacks(UserSettings $settings, array $stacks): bool
    {
        foreach ($settings->getTargetJobs() as $target) {
            $normalizedTarget = mb_strtolower($target);
            $matchesAll = true;

            foreach ($stacks as $stack) {
                $stackMatched = false;
                foreach (self::BACKEND_STACK_PATTERNS[$stack] ?? [] as $pattern) {
                    if ($this->containsTechnology($normalizedTarget, $pattern)) {
                        $stackMatched = true;
                        break;
                    }
                }
                if (!$stackMatched) {
                    $matchesAll = false;
                    break;
                }
            }

            if ($matchesAll) {
                return true;
            }
        }

        return false;
    }

    private function roleMatchesExplicitNonPhpTarget(string $role, UserSettings $settings): bool
    {
        if (trim($role) === '') {
            return false;
        }

        $normalizedRole = mb_strtolower($role);
        foreach ($settings->getTargetJobs() as $target) {
            if ($this->targetIsPhp($target)) {
                continue;
            }
            if ($this->titleCompatibilityScore($normalizedRole, $target) >= 25) {
                return true;
            }
        }

        return false;
    }

    private function targetIsPhp(string $target): bool
    {
        $normalizedTarget = mb_strtolower($target);
        foreach (self::BACKEND_STACK_PATTERNS['PHP/Symfony'] as $pattern) {
            if ($this->containsTechnology($normalizedTarget, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function titleCompatibilityScore(string $jobTitle, string $target): int
    {
        $tokens = $this->tokens($target);
        if ($tokens === []) {
            return 0;
        }

        $specificTokens = array_values(array_filter(
            $tokens,
            static fn(string $token): bool => !in_array($token, self::GENERIC_ROLE_TOKENS, true),
        ));
        $genericTokens = array_values(array_filter(
            $tokens,
            static fn(string $token): bool => in_array($token, self::GENERIC_ROLE_TOKENS, true),
        ));

        if ($specificTokens === []) {
            return $this->tokenMatchScore($jobTitle, $genericTokens, 10);
        }

        return min(
            35,
            $this->tokenMatchScore($jobTitle, $specificTokens, 30)
            + $this->tokenMatchScore($jobTitle, $genericTokens, 5),
        );
    }

    /** @param list<string> $tokens */
    private function tokenMatchScore(string $text, array $tokens, int $maximum): int
    {
        if ($tokens === []) {
            return 0;
        }

        $matches = count(array_filter(
            $tokens,
            fn(string $token): bool => $this->containsTechnology($text, $token),
        ));

        return (int) round(($matches / count($tokens)) * $maximum);
    }

    /**
     * @return array{mode: self::STACK_RELATION_*, stacks: list<string>}|null
     */
    private function detectExplicitStackRelation(JobOffer $job): ?array
    {
        $text = $job->getTitle()."\n".mb_substr($job->getDescription(), 0, 1800);
        $segments = preg_split('/(?<=[.!?;])\s+|\R+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $alternative = null;
        $mixedRequired = null;

        foreach ($segments as $segment) {
            $stacks = $this->detectStacksInText($segment, true);
            if (count($stacks) < 2 || !in_array('PHP/Symfony', $stacks, true)) {
                continue;
            }

            if (preg_match('/nice[- ]?to[- ]?have|optional|optionnel|facultatif|bonus|un plus|atout|appr[eé]ci[eé]e?s?|souhait[eé]e?s?|legacy|ancien(?:ne)?|migration/iu', $segment) === 1) {
                continue;
            }

            $hasAlternativeCue = preg_match('/\b(?:or|ou|either)\b|au choix|one of|l[’\']un ou l[’\']autre|l[’\']une ou l[’\']autre/iu', $segment) === 1;
            $hasCumulativeCue = preg_match('/\b(?:and|et|both)\b|ainsi que|les deux|tous les deux|&|\+/iu', $segment) === 1;
            $hasRequiredCue = preg_match('/\b(?:required|mandatory|must|requires?|requis(?:e|es|s)?|obligatoire(?:s)?|indispensable(?:s)?|exig[eé]e?s?)\b/iu', $segment) === 1;

            if ($hasCumulativeCue && $hasRequiredCue) {
                $mixedRequired = [
                    'mode' => self::STACK_RELATION_MIXED_REQUIRED,
                    'stacks' => $stacks,
                ];
                continue;
            }

            if ($hasAlternativeCue) {
                $alternative = [
                    'mode' => self::STACK_RELATION_ALTERNATIVE,
                    'stacks' => $stacks,
                ];
            }
        }

        return $mixedRequired ?? $alternative;
    }

    /** @return list<string> */
    private function detectStacksInText(string $text, bool $relationshipContext = false): array
    {
        $normalized = mb_strtolower($text);
        $detected = [];

        foreach (self::BACKEND_STACK_PATTERNS as $stack => $patterns) {
            foreach ($patterns as $pattern) {
                if ($this->containsTechnology($normalized, $pattern)) {
                    $detected[] = $stack;
                    continue 2;
                }
            }

            if ($relationshipContext && $stack === 'Go' && $this->containsTechnology($normalized, 'go')) {
                $detected[] = $stack;
            }
        }

        return $detected;
    }

    /** @return list<string> */
    private function detectPrimaryBackendStacks(JobOffer $job): array
    {
        $title = mb_strtolower($job->getTitle());
        $description = mb_strtolower($job->getDescription());
        $openingDescription = mb_substr($description, 0, 800);
        $scores = [];

        foreach (self::BACKEND_STACK_PATTERNS as $stack => $patterns) {
            $score = 0;
            foreach ($patterns as $pattern) {
                if ($this->containsTechnology($title, $pattern)) {
                    $score += 4;
                }
                if ($this->containsTechnology($openingDescription, $pattern)) {
                    $score += 2;
                }
                if ($this->containsTechnology($description, $pattern)) {
                    $score += 1;
                }
            }
            if ($score > 0) {
                $scores[$stack] = $score;
            }
        }

        if ($scores === []) {
            return [];
        }

        $highest = max($scores);
        if ($highest < 3) {
            return [];
        }

        return array_keys(array_filter($scores, static fn(int $score): bool => $score === $highest));
    }

    /** @return list<string> */
    private function detectPreferredBackendStacks(UserSettings $settings): array
    {
        $scores = [];

        foreach (self::BACKEND_STACK_PATTERNS as $stack => $patterns) {
            $score = 0;
            foreach ($settings->getTargetJobs() as $target) {
                foreach ($patterns as $pattern) {
                    if ($this->containsTechnology(mb_strtolower($target), $pattern)) {
                        $score += 3;
                    }
                }
            }
            foreach ($settings->getSkills() as $skill) {
                foreach ($patterns as $pattern) {
                    if ($this->containsTechnology(mb_strtolower($skill), $pattern)) {
                        $score += 1;
                    }
                }
            }
            if ($score > 0) {
                $scores[$stack] = $score;
            }
        }

        if ($scores === []) {
            return [];
        }

        $highest = max($scores);

        return array_keys(array_filter($scores, static fn(int $score): bool => $score === $highest));
    }

    private function containsTechnology(string $text, string $technology): bool
    {
        $quoted = preg_quote($technology, '/');

        return preg_match('/(?<![\pL\pN])'.$quoted.'(?![\pL\pN])/iu', $text) === 1;
    }

    private function tokens(string $value): array
    {
        $tokens = preg_split('/[^\pL\pN+#.]+/u', mb_strtolower($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_filter($tokens, static fn(string $token): bool => mb_strlen($token) >= 3));
    }

    private function contractScore(string $contract): int
    {
        $value = mb_strtolower($contract);
        foreach (['cdi','cdd','freelance','portage','sous-traitance','mission'] as $accepted) {
            if (str_contains($value, $accepted)) return 5;
        }
        return 2;
    }

    private function freshnessScore(?\DateTimeImmutable $publishedAt): int
    {
        if ($publishedAt === null) return 0;
        $hours = max(0, (time() - $publishedAt->getTimestamp()) / 3600);
        return $hours <= 72 ? 2 : ($hours <= 168 ? 1 : 0);
    }
}
