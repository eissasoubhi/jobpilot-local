<?php

declare(strict_types=1);

namespace App\Messaging\Application;

final class PlainTextJobAlertLinkExtractor
{
    private const MAX_CONTEXT_LENGTH = 2_500;

    /** @return list<array{url: string, label: string, context: string}> */
    public function extract(string $plainBody): array
    {
        if (trim($plainBody) === '') {
            return [];
        }

        $lines = preg_split('/\R/u', $plainBody) ?: [];
        $result = [];

        foreach ($lines as $index => $line) {
            if (preg_match_all('~https?://[^\s<>"\']+~iu', $line, $matches) !== 1 && empty($matches[0])) {
                continue;
            }

            foreach ($matches[0] ?? [] as $rawUrl) {
                $url = rtrim((string) $rawUrl, '.,;)\]');
                $blockLines = $this->contextLines($lines, $index, $url);
                $context = mb_substr($this->cleanText(implode(' ', $blockLines)), 0, self::MAX_CONTEXT_LENGTH);
                $result[] = [
                    'url' => $url,
                    'label' => $this->titleCandidate($blockLines),
                    'context' => $context,
                ];
            }
        }

        return $result;
    }

    /** @param list<string> $lines @return list<string> */
    private function contextLines(array $lines, int $urlLineIndex, string $url): array
    {
        $before = [];
        for ($index = $urlLineIndex - 1; $index >= 0 && count($before) < 2; --$index) {
            $line = $this->cleanText((string) ($lines[$index] ?? ''));
            if ($line === '') {
                break;
            }
            if (preg_match('~https?://~i', $line) === 1) {
                break;
            }
            array_unshift($before, $line);
        }

        $current = $this->cleanText(str_replace($url, ' ', (string) ($lines[$urlLineIndex] ?? '')));
        if ($current !== '') {
            $before[] = $current;
        }

        return $before;
    }

    /** @param list<string> $lines */
    private function titleCandidate(array $lines): string
    {
        foreach ($lines as $line) {
            $candidate = preg_replace('/^[\s>*•·\-]+/u', '', $this->cleanText($line)) ?? $line;
            $parts = preg_split('/\s+(?:\||—|–|-)\s+/u', $candidate, 2) ?: [$candidate];
            $candidate = trim((string) ($parts[0] ?? ''));
            if ($this->usableTitle($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function usableTitle(string $candidate): bool
    {
        $length = mb_strlen($candidate);
        if ($length < 6 || $length > 180) {
            return false;
        }

        $normalized = mb_strtolower($candidate);
        foreach (['voir l offre', 'voir l’offre', 'postuler', 'apply now', 'view job', 'en savoir plus', 'se désabonner'] as $generic) {
            if ($normalized === $generic) {
                return false;
            }
        }

        return preg_match('~https?://~i', $candidate) !== 1;
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
