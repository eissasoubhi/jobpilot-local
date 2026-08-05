<?php

declare(strict_types=1);

namespace App\Service;

final class CoverLetterRequirementDetector
{
    public function isRequired(string $text): bool
    {
        $text = preg_replace('/\s+/u', ' ', mb_strtolower(trim($text))) ?? '';
        if ($text === '') {
            return false;
        }

        $negativePatterns = [
            '/\b(?:sans|aucune?)\s+(?:lettre de motivation|lettre de candidature|lettre de présentation)\b/u',
            '/\b(?:lettre de motivation|lettre de candidature|lettre de présentation)\s+(?:non requise|non demandée|facultative|optionnelle)\b/u',
            '/\b(?:no|without)\s+(?:cover letter|motivation letter)\b/u',
            '/\b(?:cover letter|motivation letter)\s+(?:is\s+)?(?:not required|not requested|optional)\b/u',
        ];

        foreach ($negativePatterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return false;
            }
        }

        $positivePatterns = [
            '/\b(?:lettre de motivation|lettre de candidature|lettre de présentation|courrier de motivation)\b/u',
            '/\b(?:cover letter|motivation letter)\b/u',
            '/\bcv\s*(?:et|\+|ainsi qu[’\']une?)\s+(?:une\s+)?lettre\b/u',
            '/\b(?:resume|résumé)\s*(?:and|\+)\s+(?:a\s+)?cover letter\b/u',
        ];

        foreach ($positivePatterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
