<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Html;

final class GenericHtmlModeDetector
{
    /** @return array<string, mixed> */
    public function analyze(string $html): array
    {
        $document = $this->document($html);
        $xpath = new \DOMXPath($document);

        $visibleText = $this->visibleText($document);
        $visibleTextCharacters = mb_strlen($visibleText);
        $jobStructuredData = $this->jobStructuredDataCount($xpath);
        $jobLikeLinks = $this->jobLikeLinksCount($xpath);
        $scriptTags = $xpath->query('//script')?->length ?? 0;
        $javascriptMarkers = $this->javascriptMarkers($html, $xpath);
        $jobKeywordHits = $this->jobKeywordHits($visibleText);
        $emptyAppShell = $this->hasEmptyAppShell($xpath);

        $recommendedMode = 'HTTP';
        $confidence = 'LOW';
        $browserVerificationRequired = false;
        $reason = 'Le HTML contient du contenu visible, mais pas assez de signaux pour confirmer la présence d’offres. HTTP reste le choix le plus léger pour commencer.';

        if ($jobStructuredData > 0) {
            $confidence = 'HIGH';
            $reason = 'Le HTML reçu contient au moins un objet structuré JobPosting : les offres sont déjà disponibles sans exécuter JavaScript.';
        } elseif ($jobLikeLinks >= 2) {
            $confidence = 'HIGH';
            $reason = 'Le HTML reçu contient plusieurs liens qui ressemblent à des fiches d’offres : HTTP suffit probablement.';
        } elseif ($visibleTextCharacters >= 1_500 && $jobKeywordHits >= 3) {
            $confidence = 'MEDIUM';
            $reason = 'Le HTML serveur contient déjà beaucoup de texte et plusieurs termes liés aux offres : HTTP est recommandé.';
        } elseif ($javascriptMarkers >= 2 && $visibleTextCharacters < 800 && $jobLikeLinks === 0 && $jobStructuredData === 0) {
            $recommendedMode = 'BROWSER';
            $confidence = 'HIGH';
            $browserVerificationRequired = true;
            $reason = 'La réponse ressemble à une coquille d’application JavaScript avec très peu de contenu exploitable : Browser/Playwright est probablement nécessaire.';
        } elseif ($emptyAppShell && $scriptTags >= 2 && $visibleTextCharacters < 600 && $jobLikeLinks === 0) {
            $recommendedMode = 'BROWSER';
            $confidence = 'MEDIUM';
            $browserVerificationRequired = true;
            $reason = 'Le conteneur principal de l’application est presque vide et la page dépend de scripts : une vérification Browser/Playwright est recommandée.';
        } else {
            $browserVerificationRequired = $jobStructuredData === 0 && $jobLikeLinks === 0;
        }

        return [
            'recommendedMode' => $recommendedMode,
            'confidence' => $confidence,
            'reason' => $reason,
            'browserVerificationRequired' => $browserVerificationRequired,
            'signals' => [
                'visibleTextCharacters' => $visibleTextCharacters,
                'jobStructuredData' => $jobStructuredData,
                'jobLikeLinks' => $jobLikeLinks,
                'jobKeywordHits' => $jobKeywordHits,
                'scriptTags' => $scriptTags,
                'javascriptMarkers' => $javascriptMarkers,
                'emptyAppShell' => $emptyAppShell,
            ],
        ];
    }

    private function document(string $html): \DOMDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8">'.$html,
                LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            throw new \RuntimeException('La réponse du site ne contient pas un document HTML exploitable.');
        }

        return $document;
    }

    private function visibleText(\DOMDocument $document): string
    {
        $clone = clone $document;
        $xpath = new \DOMXPath($clone);
        foreach (['//script', '//style', '//noscript', '//template', '//svg'] as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes instanceof \DOMNodeList) {
                continue;
            }
            for ($index = $nodes->length - 1; $index >= 0; --$index) {
                $node = $nodes->item($index);
                $node?->parentNode?->removeChild($node);
            }
        }

        return $this->clean($clone->textContent ?? '');
    }

    private function jobStructuredDataCount(\DOMXPath $xpath): int
    {
        $nodes = $xpath->query('//script[contains(translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "ld+json")]');
        if (!$nodes instanceof \DOMNodeList) {
            return 0;
        }

        $count = 0;
        foreach ($nodes as $node) {
            if (preg_match('/["\']@type["\']\s*:\s*["\']JobPosting["\']/iu', $node->textContent ?? '') === 1) {
                ++$count;
            }
        }

        return $count;
    }

    private function jobLikeLinksCount(\DOMXPath $xpath): int
    {
        $links = $xpath->query('//a[@href]');
        if (!$links instanceof \DOMNodeList) {
            return 0;
        }

        $count = 0;
        foreach ($links as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }
            $href = strtolower(trim($link->getAttribute('href')));
            $text = strtolower($this->clean($link->textContent ?? ''));
            if (preg_match('~/(job|jobs|offre|offres|emploi|emplois|position|positions|mission|missions)(/|\?|$)~iu', $href) === 1
                || preg_match('/\b(voir l.offre|voir le poste|postuler|cdi|cdd|freelance|mission)\b/iu', $text) === 1) {
                ++$count;
            }
        }

        return $count;
    }

    private function javascriptMarkers(string $html, \DOMXPath $xpath): int
    {
        $markers = 0;
        foreach ([
            '/__NEXT_DATA__/i',
            '/\/_next\//i',
            '/webpackChunk/i',
            '/__NUXT__/i',
            '/\/_nuxt\//i',
            '/data-reactroot/i',
            '/ng-version=/i',
            '/id=["\'](?:root|app|__next)["\']/i',
        ] as $pattern) {
            if (preg_match($pattern, $html) === 1) {
                ++$markers;
            }
        }

        if (($xpath->query('//script[@type="module"]')?->length ?? 0) > 0) {
            ++$markers;
        }

        return $markers;
    }

    private function hasEmptyAppShell(\DOMXPath $xpath): bool
    {
        $nodes = $xpath->query('//*[@id="root" or @id="app" or @id="__next"]');
        if (!$nodes instanceof \DOMNodeList) {
            return false;
        }

        foreach ($nodes as $node) {
            if (mb_strlen($this->clean($node->textContent ?? '')) < 120) {
                return true;
            }
        }

        return false;
    }

    private function jobKeywordHits(string $text): int
    {
        preg_match_all('/\b(offre|emploi|poste|mission|cdi|cdd|freelance|recrutement|job)\b/iu', $text, $matches);

        return min(50, count($matches[0] ?? []));
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
