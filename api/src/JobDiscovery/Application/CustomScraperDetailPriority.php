<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

final class CustomScraperDetailPriority
{
    /**
     * @param list<array<string, mixed>> $candidates
     * @param list<string> $targetJobs
     * @param list<string> $skills
     * @return list<int>
     */
    public function rank(array $candidates, array $targetJobs, array $skills): array
    {
        $profile = $this->profileTerms($targetJobs, $skills);
        $ranked = [];

        foreach ($candidates as $index => $candidate) {
            $title = $this->normalize((string) ($candidate['title'] ?? ''));
            $description = $this->normalize((string) ($candidate['description'] ?? ''));
            $score = 0;
            $matches = [];

            foreach ($profile['phrases'] as $phrase) {
                if ($this->containsTerm($title, $phrase)) {
                    $score += 30;
                    $matches[$phrase] = true;
                } elseif ($description !== '' && $this->containsTerm($description, $phrase)) {
                    $score += 8;
                    $matches[$phrase] = true;
                }
            }

            foreach ($profile['tokens'] as $token) {
                if ($this->containsTerm($title, $token)) {
                    $score += 8;
                    $matches[$token] = true;
                } elseif ($description !== '' && $this->containsTerm($description, $token)) {
                    $score += 2;
                    $matches[$token] = true;
                }
            }

            $ranked[] = [
                'index' => $index,
                'score' => $score,
                'matches' => array_keys($matches),
            ];
        }

        usort($ranked, static function (array $left, array $right): int {
            $scoreOrder = $right['score'] <=> $left['score'];
            if ($scoreOrder !== 0) {
                return $scoreOrder;
            }

            return $left['index'] <=> $right['index'];
        });

        return array_map(
            static fn (array $entry): int => (int) $entry['index'],
            $ranked,
        );
    }

    /**
     * @param list<string> $targetJobs
     * @param list<string> $skills
     * @return array{phrases: list<string>, tokens: list<string>}
     */
    private function profileTerms(array $targetJobs, array $skills): array
    {
        $phrases = [];
        $tokens = [];
        $ignored = [
            'developer', 'developpeur', 'developpeuse', 'senior', 'junior', 'lead', 'tech', 'engineer',
            'ingenieur', 'backend', 'frontend', 'fullstack', 'full', 'stack', 'web', 'software', 'remote',
        ];

        foreach ($targetJobs as $value) {
            $normalized = $this->normalize((string) $value);
            if ($normalized !== '' && mb_strlen($normalized) >= 4) {
                $phrases[$normalized] = true;
            }
        }

        foreach (array_merge($targetJobs, $skills) as $value) {
            $normalized = $this->normalize((string) $value);
            foreach (preg_split('/[^a-z0-9.+#]+/', $normalized) ?: [] as $token) {
                if (strlen($token) < 2 || in_array($token, $ignored, true)) {
                    continue;
                }
                $tokens[$token] = true;
            }
        }

        return [
            'phrases' => array_keys($phrases),
            'tokens' => array_keys($tokens),
        ];
    }

    private function containsTerm(string $haystack, string $needle): bool
    {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        return preg_match('/(?<![a-z0-9])'.preg_quote($needle, '/').'(?![a-z0-9])/', $haystack) === 1;
    }

    private function normalize(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return strtolower($ascii === false ? $value : $ascii);
    }
}
