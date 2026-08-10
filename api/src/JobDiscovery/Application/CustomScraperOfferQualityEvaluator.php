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
                'reasons' => ['Titre absent, trop court ou générique.'],
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

        return preg_match('/^(offre|emploi|job|mission|poste|voir|voir l offre|voir le poste|postuler|candidater|details?|en savoir plus)$/', $normalized) !== 1;
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
