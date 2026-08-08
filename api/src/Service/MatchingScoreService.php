<?php

namespace App\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\Ai\AiJobMatchAnalyzerInterface;

final class MatchingScoreService
{
    private const PRIMARY_STACK_CONFLICT_CAP = 45;

    /** @var array<string, list<string>> */
    private const BACKEND_STACK_PATTERNS = [
        'PHP/Symfony' => ['php', 'symfony', 'laravel'],
        'Java/Spring' => ['java', 'spring', 'spring boot', 'quarkus'],
        'Python' => ['python', 'django', 'fastapi', 'flask'],
        '.NET/C#' => ['.net', 'dotnet', 'c#', 'asp.net'],
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
            return [
                'score' => $aiAnalysis->score,
                'reasons' => $aiAnalysis->toScoreReasons(),
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

        $primaryStacks = $this->detectPrimaryBackendStacks($job);
        if ($primaryStacks !== []) {
            $reasons[] = 'Stack principale détectée : '.implode(' ou ', $primaryStacks);

            $preferredStacks = $this->detectPreferredBackendStacks($settings);
            if ($preferredStacks !== [] && array_intersect($primaryStacks, $preferredStacks) === []) {
                $score = min($score, self::PRIMARY_STACK_CONFLICT_CAP);
                $reasons[] = 'Conflit de stack principale avec le profil : score plafonné à '.self::PRIMARY_STACK_CONFLICT_CAP.'/100';
            }
        }

        return ['score' => $score, 'reasons' => $reasons, 'hardRejected' => false];
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
