<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

final class CustomScraperOfferQualityEvaluator
{
    /**
     * @param array<string, mixed> $candidate
     * @return array{reliable: bool, score: int, reasons: list<string>}
     */
    public function evaluate(array $candidate, string $expectedDomain): array
    {
        $reasons = [];
        $score = 0;
        $title = $this->clean((string) ($candidate['title'] ?? ''));
        $description = $this->clean((string) ($candidate['description'] ?? ''));
        $sourceUrl = trim((string) ($candidate['sourceUrl'] ?? ''));

        if (!$this->validTitle($title)) {
            return [
                'reliable' => false,
                'score' => 0,
                'reasons' => ['Titre absent, trop court, générique ou contaminé par le contenu de la page.'],
            ];
        }
        $score += mb_strlen($title) >= 10 ? 25 : 15;
        $reasons[] = 'Titre exploitable.';

        if (!$this->sameHttpsDomain($sourceUrl, $expectedDomain)) {
            return [
                'reliable' => false,
                'score' => min(25, $score),
                'reasons' => [...$reasons, 'URL de fiche absente ou hors du domaine autorisé.'],
            ];
        }
        $score += 20;
        $reasons[] = 'URL HTTPS du domaine autorisé.';

        $rawData = is_array($candidate['rawData'] ?? null) ? $candidate['rawData'] : [];
        $listingMethod = (string) ($rawData['extractionMethod'] ?? '');
        $detailMethod = (string) ($rawData['detailExtractionMethod'] ?? '');
        $structured = $listingMethod === 'JSON_LD' || $detailMethod === 'JSON_LD';
        $detailEnriched = ($rawData['detailEnriched'] ?? false) === true;

        if ($structured) {
            $score += 25;
            $reasons[] = 'Données Schema.org JobPosting détectées.';
        } elseif ($detailEnriched) {
            $score += 15;
            $reasons[] = 'Fiche détail HTTP enrichie.';
        }

        $descriptionLength = mb_strlen($description);
        if (!$structured && $detailEnriched && $descriptionLength >= 60) {
            $distinctiveTokens = $this->distinctiveTitleTokens($title);
            if ($distinctiveTokens !== [] && !$this->containsAnyToken($description, $distinctiveTokens)) {
                return [
                    'reliable' => false,
                    'score' => min(55, $score),
                    'reasons' => [
                        ...$reasons,
                        'La fiche détail non structurée ne contient aucun terme distinctif du titre : contenu de page potentiellement contaminé.',
                    ],
                ];
            }
        }

        if ($descriptionLength >= 200) {
            $score += 25;
            $reasons[] = 'Description détaillée.';
        } elseif ($descriptionLength >= 80) {
            $score += 18;
            $reasons[] = 'Description exploitable.';
        } elseif ($descriptionLength >= 40) {
            $score += 8;
            $reasons[] = 'Description courte mais présente.';
        } else {
            $reasons[] = 'Description trop courte pour un import automatique.';
        }

        $metadata = 0;
        foreach (['company', 'location', 'contractType', 'workMode', 'publishedAt'] as $key) {
            if ($this->clean((string) ($candidate[$key] ?? '')) !== '') {
                ++$metadata;
            }
        }
        if (($candidate['salaryMin'] ?? null) !== null || ($candidate['salaryMax'] ?? null) !== null
            || ($candidate['tjmMin'] ?? null) !== null || ($candidate['tjmMax'] ?? null) !== null) {
            ++$metadata;
        }
        if ($metadata > 0) {
            $score += min(10, $metadata * 2);
            $reasons[] = sprintf('%d champ(s) métier supplémentaire(s) renseigné(s).', $metadata);
        }

        $score = min(100, $score);
        $reliable = $score >= 70 && $descriptionLength >= 60;
        if (!$reliable) {
            $reasons[] = 'Seuil d’import automatique non atteint.';
        } else {
            $reasons[] = 'Candidat suffisamment fiable pour le pipeline canonique.';
        }

        return [
            'reliable' => $reliable,
            'score' => $score,
            'reasons' => $reasons,
        ];
    }

    private function validTitle(string $title): bool
    {
        if (mb_strlen($title) < 5) {
            return false;
        }

        $normalized = $this->normalize($title);
        if (preg_match('/^(offre|emploi|job|mission|poste|voir|voir l offre|voir le poste|postuler|candidater|details?|en savoir plus)$/', $normalized) === 1) {
            return false;
        }

        return preg_match('/\b(?:offre publiee il y a|missions entreprises candidats|candidats freelances|retour vers)\b/', $normalized) !== 1;
    }

    /** @return list<string> */
    private function distinctiveTitleTokens(string $title): array
    {
        $normalized = $this->tokenText($title);
        $generic = array_fill_keys([
            'developpeur', 'developpeuse', 'developer', 'developper', 'engineer', 'ingenieur', 'ingenieure',
            'senior', 'junior', 'lead', 'tech', 'technical', 'full', 'stack', 'fullstack', 'backend', 'frontend',
            'front', 'back', 'software', 'cloud', 'web', 'application', 'applications', 'product', 'manager', 'consultant',
            'architecte', 'architect', 'responsable', 'expert', 'specialiste', 'specialist', 'h', 'f', 'hf', 'fh', 'cdi',
            'cdd', 'freelance', 'contractor', 'stage', 'alternance', 'remote', 'hybride', 'hybrid', 'paris', 'france',
        ], true);

        $tokens = [];
        foreach (preg_split('/\s+/', $normalized) ?: [] as $token) {
            if (strlen($token) < 3 || ctype_digit($token) || isset($generic[$token])) {
                continue;
            }
            $tokens[$token] = true;
        }

        return array_keys($tokens);
    }

    /** @param list<string> $tokens */
    private function containsAnyToken(string $description, array $tokens): bool
    {
        $description = ' '.$this->tokenText($description).' ';
        foreach ($tokens as $token) {
            if (str_contains($description, ' '.$token.' ')) {
                return true;
            }
        }

        return false;
    }

    private function tokenText(string $value): string
    {
        $value = mb_strtolower($this->clean($value));
        $value = str_replace(
            ['next.js', 'node.js', 'vue.js', '.net', 'c#'],
            ['nextjs', 'nodejs', 'vuejs', ' dotnet ', ' csharp '],
            $value,
        );
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = strtolower($ascii === false ? $value : $ascii);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value);
    }

    private function sameHttpsDomain(string $url, string $expectedDomain): bool
    {
        $parts = parse_url($url);

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === strtolower(trim($expectedDomain));
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function normalize(string $value): string
    {
        $value = $this->clean($value);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return strtolower($ascii === false ? $value : $ascii);
    }
}
