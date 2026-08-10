<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

final class JobTextMetadataExtractor
{
    /** @return array{contractType: string, workMode: string, tjmMin: int|null, tjmMax: int|null} */
    public function extract(string $text): array
    {
        $contractType = $this->contractType($text);
        $workMode = $this->workMode($text);
        $tjm = $this->tjm($text);

        return [
            'contractType' => $contractType,
            'workMode' => $workMode,
            'tjmMin' => $tjm['min'],
            'tjmMax' => $tjm['max'],
        ];
    }

    private function contractType(string $text): string
    {
        foreach ([
            'CDI' => '/\bCDI\b/iu',
            'CDD' => '/\bCDD\b/iu',
            'Freelance' => '/\b(freelance|ind[ée]pendant|mission)\b/iu',
            'Alternance' => '/\b(alternance|apprentissage)\b/iu',
            'Stage' => '/\bstage\b/iu',
            'Intérim' => '/\bint[ée]rim\b/iu',
        ] as $label => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $label;
            }
        }

        return '';
    }

    private function workMode(string $text): string
    {
        if (preg_match('/\b(full\s*remote|remote\s*only|100\s*%\s*(?:remote|t[ée]l[ée]travail)|t[ée]l[ée]travail\s+complet)\b/iu', $text) === 1) {
            return 'Télétravail';
        }
        if (preg_match('/\b(hybride|hybrid|[1-5]\s*j(?:our)?s?\s*(?:de\s*)?t[ée]l[ée]travail)\b/iu', $text) === 1) {
            return 'Hybride';
        }
        if (preg_match('/\b(sur\s+site|on\s*site|pr[ée]sentiel)\b/iu', $text) === 1) {
            return 'Sur site';
        }

        return '';
    }

    /** @return array{min: int|null, max: int|null} */
    private function tjm(string $text): array
    {
        if (preg_match('/\bTJM\b\s*:?\s*([0-9]{2,4})\s*(?:[-–à]\s*([0-9]{2,4}))?\s*(?:€|EUR)?/iu', $text, $matches) === 1) {
            $min = (int) $matches[1];
            $max = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : $min;

            return ['min' => $min, 'max' => $max];
        }
        if (preg_match('/\b([0-9]{2,4})\s*(?:[-–à]\s*([0-9]{2,4})\s*)?(?:€|EUR)\s*(?:\/|par\s*)?(?:j|jour)\b/iu', $text, $matches) === 1) {
            $min = (int) $matches[1];
            $max = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : $min;

            return ['min' => $min, 'max' => $max];
        }

        return ['min' => null, 'max' => null];
    }
}
