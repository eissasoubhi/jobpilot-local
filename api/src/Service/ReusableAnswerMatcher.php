<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ReusableAnswer;

final class ReusableAnswerMatcher
{
    /**
     * @param iterable<ReusableAnswer> $answers
     * @return list<array{answer: ReusableAnswer, score: float, matchedPattern: string}>
     */
    public function match(string $question, string $language, iterable $answers): array
    {
        $question = $this->normalize($question);
        if ($question === '') {
            return [];
        }

        $language = strtolower(trim($language));
        if (!in_array($language, ['fr', 'en'], true)) {
            $language = 'fr';
        }

        $matches = [];
        foreach ($answers as $answer) {
            if (!$answer->isEnabled()) {
                continue;
            }

            $patterns = $answer->getQuestionPatterns()[$language] ?? [];
            foreach ($patterns as $pattern) {
                $score = $this->score($question, $this->normalize($pattern));
                if ($score < 0.6) {
                    continue;
                }

                if (!isset($matches[$answer->getKey()]) || $score > $matches[$answer->getKey()]['score']) {
                    $matches[$answer->getKey()] = [
                        'answer' => $answer,
                        'score' => $score,
                        'matchedPattern' => $pattern,
                    ];
                }
            }
        }

        $matches = array_values($matches);
        usort($matches, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        return array_slice($matches, 0, 5);
    }

    private function score(string $question, string $pattern): float
    {
        if ($question === '' || $pattern === '') {
            return 0.0;
        }
        if ($question === $pattern) {
            return 1.0;
        }
        if (str_contains($question, $pattern) || str_contains($pattern, $question)) {
            $ratio = min(mb_strlen($question), mb_strlen($pattern)) / max(mb_strlen($question), mb_strlen($pattern));
            if ($ratio >= 0.65) {
                return min(0.95, 0.8 + (0.15 * $ratio));
            }
        }

        $questionTokens = $this->tokens($question);
        $patternTokens = $this->tokens($pattern);
        if ($questionTokens === [] || $patternTokens === []) {
            return 0.0;
        }

        $intersection = array_intersect($questionTokens, $patternTokens);
        $union = array_unique([...$questionTokens, ...$patternTokens]);
        $jaccard = count($intersection) / count($union);

        return $jaccard >= 0.45 ? min(0.85, 0.45 + (0.4 * $jaccard)) : 0.0;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['’', "'", '–', '—'], ' ', $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        $stopWords = [
            'a', 'au', 'aux', 'avec', 'de', 'des', 'du', 'en', 'est', 'et', 'la', 'le', 'les', 'un', 'une', 'votre', 'vous',
            'a', 'an', 'and', 'are', 'do', 'is', 'of', 'the', 'to', 'what', 'you', 'your',
        ];

        $tokens = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            static fn (string $token): bool => mb_strlen($token) > 1 && !in_array($token, $stopWords, true),
        )));
    }
}
