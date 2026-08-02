<?php

namespace App\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;

final class MatchingScoreService
{
    public function evaluate(JobOffer $job, UserSettings $settings): array
    {
        $text = mb_strtolower($job->getTitle().' '.$job->getDescription());
        $reasons = [];

        foreach ($settings->getExclusions() as $excluded) {
            if ($excluded !== '' && str_contains($text, mb_strtolower($excluded))) {
                return ['score' => 0, 'reasons' => ['Exclusion détectée : '.$excluded], 'hardRejected' => true];
            }
        }

        $titleScore = 0;
        foreach ($settings->getTargetJobs() as $target) {
            $tokens = $this->tokens($target);
            $matches = count(array_filter($tokens, static fn(string $token): bool => str_contains($text, $token)));
            if ($tokens !== []) $titleScore = max($titleScore, (int) round(($matches / count($tokens)) * 35));
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

        return ['score' => $score, 'reasons' => $reasons, 'hardRejected' => false];
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
