<?php

declare(strict_types=1);

namespace App\Service;

final class CustomScraperSearchResultMerger
{
    /**
     * @param list<array{keyword: ?string, candidates: list<array<string, mixed>>}> $batches
     * @return array{candidates: list<array<string, mixed>>, rawCount: int, duplicateCount: int}
     */
    public function merge(array $batches): array
    {
        $candidatesByKey = [];
        $keywordsByKey = [];
        $rawCount = 0;

        foreach ($batches as $batch) {
            $keyword = is_string($batch['keyword'] ?? null) ? trim((string) $batch['keyword']) : null;
            if ($keyword === '') {
                $keyword = null;
            }

            foreach ($batch['candidates'] ?? [] as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }

                ++$rawCount;
                $key = $this->candidateKey($candidate);
                $keywordsByKey[$key] ??= [];
                foreach ($this->existingKeywords($candidate) as $existingKeyword) {
                    if (!in_array($existingKeyword, $keywordsByKey[$key], true)) {
                        $keywordsByKey[$key][] = $existingKeyword;
                    }
                }
                if ($keyword !== null && !in_array($keyword, $keywordsByKey[$key], true)) {
                    $keywordsByKey[$key][] = $keyword;
                }

                if (!isset($candidatesByKey[$key])
                    || $this->candidateRichness($candidate) > $this->candidateRichness($candidatesByKey[$key])) {
                    $candidatesByKey[$key] = $candidate;
                }
            }
        }

        foreach ($candidatesByKey as $key => $candidate) {
            $rawData = is_array($candidate['rawData'] ?? null) ? $candidate['rawData'] : [];
            $candidate['rawData'] = [
                ...$rawData,
                'discoveredByKeywords' => $keywordsByKey[$key] ?? [],
            ];
            $candidatesByKey[$key] = $candidate;
        }

        $candidates = array_values($candidatesByKey);

        return [
            'candidates' => $candidates,
            'rawCount' => $rawCount,
            'duplicateCount' => max(0, $rawCount - count($candidates)),
        ];
    }

    /** @param array<string, mixed> $candidate */
    private function candidateKey(array $candidate): string
    {
        $externalId = trim((string) ($candidate['externalId'] ?? ''));
        if ($externalId !== '') {
            return 'id:'.$externalId;
        }

        $sourceUrl = trim((string) ($candidate['sourceUrl'] ?? ''));
        $title = trim((string) ($candidate['title'] ?? ''));

        return 'url:'.hash('sha256', $sourceUrl.'|'.$title);
    }

    /** @param array<string, mixed> $candidate */
    private function candidateRichness(array $candidate): int
    {
        $score = mb_strlen(trim((string) ($candidate['description'] ?? '')));
        foreach (['company', 'location', 'contractType', 'workMode', 'publishedAt'] as $field) {
            if (trim((string) ($candidate[$field] ?? '')) !== '') {
                $score += 100;
            }
        }

        return $score;
    }

    /** @param array<string, mixed> $candidate @return list<string> */
    private function existingKeywords(array $candidate): array
    {
        $rawData = is_array($candidate['rawData'] ?? null) ? $candidate['rawData'] : [];
        $keywords = is_array($rawData['discoveredByKeywords'] ?? null)
            ? $rawData['discoveredByKeywords']
            : [];

        $result = [];
        foreach ($keywords as $keyword) {
            $keyword = is_string($keyword) ? trim($keyword) : '';
            if ($keyword !== '' && !in_array($keyword, $result, true)) {
                $result[] = $keyword;
            }
        }

        return $result;
    }
}
