<?php

declare(strict_types=1);

namespace App\Service;

final class JobDescriptionContaminationDetector
{
    public function isMultiOfferDigest(string $text): bool
    {
        $text = $this->clean($text);
        if ($text === '') {
            return false;
        }

        $normalized = $this->normalize($text);
        foreach ([
            "offres d'emploi similaires",
            'offres similaires',
            'emplois similaires',
            'offres recommandees',
            'emplois recommandes',
            'recommended jobs',
            'jobs you may be interested in',
            'jobs similar to',
        ] as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        if (preg_match_all('/\bvoir\s+l[’\']?offre(?:\s+d[’\']emploi)?\b/iu', $text, $matches) >= 2) {
            return true;
        }

        if (preg_match_all('~https?://[^\s<>"\']+~iu', $text, $matches) > 0) {
            $jobUrls = [];
            foreach ($matches[0] ?? [] as $url) {
                $url = rtrim((string) $url, '.,;)\]');
                $path = strtolower((string) parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_PATH));
                if ($path === '') {
                    continue;
                }

                if (preg_match('~/(?:comm/)?jobs/view/[^/?#]+|/emplois/[^/?#]+|/emploi/detail-offre/[^/?#]+|/detail-offre/[^/?#]+|/companies/[^/]+/jobs/[^/?#]+|/tech-it/job-mission/[^/]+/[^/?#]+|/job/[^/?#]+|/missions?/[^/?#]+~iu', $path) === 1) {
                    $jobUrls[$path] = true;
                    if (count($jobUrls) >= 2) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function localSummary(string $text, string $title): string
    {
        $text = $this->clean($text);
        $title = $this->clean($title);
        if ($title === '') {
            return '';
        }
        if (!$this->isMultiOfferDigest($text)) {
            return $text;
        }

        $position = mb_stripos($text, $title);
        if ($position === false) {
            return $title;
        }

        $candidate = mb_substr($text, $position, 2_000);
        $parts = preg_split(
            '/\bvoir\s+l[’\']?offre(?:\s+d[’\']emploi)?\b|(?:[-–—_*·.]\s*){6,}/iu',
            $candidate,
            2,
        );
        $candidate = trim((string) ($parts[0] ?? $candidate));
        $candidate = preg_replace('~https?://[^\s<>"\']+~iu', ' ', $candidate) ?? $candidate;
        $candidate = trim(preg_replace('/\s+/u', ' ', $candidate) ?? $candidate);

        if ($candidate === '' || mb_strlen($candidate) < mb_strlen($title)) {
            return $title;
        }

        return mb_substr($candidate, 0, 800);
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($this->clean($value));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return trim($ascii === false ? $value : $ascii);
    }
}
