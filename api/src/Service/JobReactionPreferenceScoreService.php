<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;
use App\Entity\JobOffer;

final class JobReactionPreferenceScoreService
{
    private const MIN_SIMILARITY = 0.25;
    private const CONFIDENCE_PRIOR = 2.5;
    private const MAX_PRIORITY_ADJUSTMENT = 6;

    /**
     * @param iterable<Application> $applications
     * @return array{score:int, adjustment:int, evidence:int, similarityWeight:float}
     */
    public function evaluate(JobOffer $job, iterable $applications): array
    {
        $signedWeight = 0.0;
        $totalWeight = 0.0;
        $evidence = 0;

        foreach ($applications as $application) {
            if (!$application instanceof Application || $this->sameJob($job, $application->getJobOffer())) {
                continue;
            }

            $signal = $this->signal($application);
            if ($signal === 0) {
                continue;
            }

            $similarity = $this->similarity($job, $application->getJobOffer());
            if ($similarity < self::MIN_SIMILARITY) {
                continue;
            }

            $signedWeight += $signal * $similarity;
            $totalWeight += $similarity;
            ++$evidence;
        }

        if ($totalWeight <= 0.0) {
            return [
                'score' => 50,
                'adjustment' => 0,
                'evidence' => 0,
                'similarityWeight' => 0.0,
            ];
        }

        $preference = max(-1.0, min(1.0, $signedWeight / $totalWeight));
        $confidence = $totalWeight / ($totalWeight + self::CONFIDENCE_PRIOR);
        $score = (int) round(50.0 + 50.0 * $preference * $confidence);
        $score = max(0, min(100, $score));
        $adjustment = (int) round((($score - 50) / 50) * self::MAX_PRIORITY_ADJUSTMENT);

        return [
            'score' => $score,
            'adjustment' => max(-self::MAX_PRIORITY_ADJUSTMENT, min(self::MAX_PRIORITY_ADJUSTMENT, $adjustment)),
            'evidence' => $evidence,
            'similarityWeight' => round($totalWeight, 3),
        ];
    }

    private function signal(Application $application): int
    {
        if ($application->getStatus() === 'IGNORED_NOT_MATCH') {
            return -1;
        }

        // submittedAt survives later inbox states (confirmation, reply, interview,
        // rejection), so a positive user decision keeps teaching the model even
        // after the application progresses through the pipeline.
        if ($application->getSubmittedAt() !== null) {
            return 1;
        }

        // OFFER_UNAVAILABLE is deliberately neutral: availability is not preference.
        return 0;
    }

    private function sameJob(JobOffer $left, JobOffer $right): bool
    {
        if ($left === $right) {
            return true;
        }

        return $left->getId() !== null
            && $right->getId() !== null
            && $left->getId() === $right->getId();
    }

    private function similarity(JobOffer $left, JobOffer $right): float
    {
        $leftTitle = $this->titleTokens($left);
        $rightTitle = $this->titleTokens($right);
        $titleSimilarity = $this->jaccard($leftTitle, $rightTitle);

        $leftTechnologies = $this->technologyTokens($left);
        $rightTechnologies = $this->technologyTokens($right);
        $hasTechnologyEvidence = $leftTechnologies !== [] || $rightTechnologies !== [];
        $technologySimilarity = $this->jaccard($leftTechnologies, $rightTechnologies);

        $contextSimilarity = $this->contextSimilarity($left, $right);

        if (!$hasTechnologyEvidence) {
            return 0.80 * $titleSimilarity + 0.20 * $contextSimilarity;
        }

        return 0.55 * $titleSimilarity + 0.35 * $technologySimilarity + 0.10 * $contextSimilarity;
    }

    /** @return list<string> */
    private function titleTokens(JobOffer $job): array
    {
        $tokens = $this->words($job->getTitle());
        $stopWords = [
            'h', 'f', 'hf', 'f-h', 'h-f', 'senior', 'junior', 'confirme', 'confirmee',
            'developpeur', 'developpeuse', 'developer', 'engineer', 'ingenieur', 'software',
            'poste', 'offre', 'emploi', 'mission', 'cdi', 'cdd', 'freelance', 'full', 'stack',
        ];

        return array_values(array_filter(
            $tokens,
            static fn (string $token): bool => mb_strlen($token) >= 2 && !in_array($token, $stopWords, true),
        ));
    }

    /** @return list<string> */
    private function technologyTokens(JobOffer $job): array
    {
        $text = $this->normalize($job->getTitle().' '.$job->getDescription());
        $technologies = [
            'php' => ['php'],
            'symfony' => ['symfony'],
            'laravel' => ['laravel'],
            'react' => ['react', 'reactjs', 'react.js'],
            'nextjs' => ['nextjs', 'next.js'],
            'vue' => ['vue', 'vuejs', 'vue.js'],
            'nuxt' => ['nuxt', 'nuxtjs', 'nuxt.js'],
            'angular' => ['angular'],
            'javascript' => ['javascript'],
            'typescript' => ['typescript'],
            'node' => ['node', 'nodejs', 'node.js'],
            'nestjs' => ['nestjs', 'nest.js'],
            'java' => ['java'],
            'spring' => ['spring', 'springboot', 'spring boot'],
            'dotnet' => ['.net', 'dotnet'],
            'csharp' => ['c#', 'csharp'],
            'python' => ['python'],
            'django' => ['django'],
            'go' => ['golang', 'go'],
            'rust' => ['rust'],
            'ruby' => ['ruby'],
            'rails' => ['rails'],
            'wordpress' => ['wordpress'],
            'drupal' => ['drupal'],
            'api-platform' => ['api platform', 'api-platform'],
        ];

        $matches = [];
        foreach ($technologies as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $normalizedAlias = $this->normalize($alias);
                if ($normalizedAlias !== '' && preg_match('/(^|[^a-z0-9])'.preg_quote($normalizedAlias, '/').'([^a-z0-9]|$)/u', $text) === 1) {
                    $matches[] = $canonical;
                    break;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    private function contextSimilarity(JobOffer $left, JobOffer $right): float
    {
        $dimensions = 0;
        $matches = 0;

        foreach ([
            [$left->getContractType(), $right->getContractType()],
            [$left->getWorkMode(), $right->getWorkMode()],
        ] as [$leftValue, $rightValue]) {
            $leftValue = $this->normalize($leftValue);
            $rightValue = $this->normalize($rightValue);
            if ($leftValue === '' || $rightValue === '') {
                continue;
            }
            ++$dimensions;
            if (str_contains($leftValue, $rightValue) || str_contains($rightValue, $leftValue)) {
                ++$matches;
            }
        }

        return $dimensions === 0 ? 0.5 : $matches / $dimensions;
    }

    /** @param list<string> $left @param list<string> $right */
    private function jaccard(array $left, array $right): float
    {
        $left = array_values(array_unique($left));
        $right = array_values(array_unique($right));
        if ($left === [] && $right === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($left, $right));
        $union = count(array_unique([...$left, ...$right]));

        return $union === 0 ? 0.0 : $intersection / $union;
    }

    /** @return list<string> */
    private function words(string $value): array
    {
        $normalized = $this->normalize($value);
        $parts = preg_split('/[^a-z0-9+#.]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($parts) ? array_values($parts) : [];
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim(strip_tags($value)));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $ascii === false ? $value : $ascii;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
