<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Html;

final class GenericJobDetailExtractor
{
    public function __construct(private GenericJobListingExtractor $listingExtractor)
    {
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    public function enrich(string $html, array $candidate, string $detailUrl, string $sourceName): array
    {
        $structured = array_values(array_filter(
            $this->listingExtractor->extract($html, $detailUrl, $sourceName),
            static fn (array $offer): bool => (string) (($offer['rawData']['extractionMethod'] ?? '')) === 'JSON_LD',
        ));

        if ($structured !== []) {
            return $this->merge($candidate, $this->bestStructuredCandidate($structured, $candidate, $detailUrl), 'JSON_LD', $detailUrl);
        }

        return $this->merge($candidate, $this->domFallback($html, $candidate, $detailUrl, $sourceName), 'DOM', $detailUrl);
    }

    /**
     * @param list<array<string, mixed>> $offers
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    private function bestStructuredCandidate(array $offers, array $candidate, string $detailUrl): array
    {
        $expectedTitle = $this->normalize((string) ($candidate['title'] ?? ''));

        foreach ($offers as $offer) {
            if (rtrim((string) ($offer['sourceUrl'] ?? ''), '/') === rtrim($detailUrl, '/')) {
                return $offer;
            }
        }

        if ($expectedTitle !== '') {
            foreach ($offers as $offer) {
                if ($this->normalize((string) ($offer['title'] ?? '')) === $expectedTitle) {
                    return $offer;
                }
            }
        }

        return $offers[0];
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    private function domFallback(string $html, array $candidate, string $detailUrl, string $sourceName): array
    {
        $document = $this->document($html);
        $xpath = new \DOMXPath($document);
        $title = $this->firstHeading($xpath);
        $description = $this->visibleContent($document, $xpath);
        $contractType = $this->contractType($description);
        $workMode = $this->workMode($description);
        $tjm = $this->tjm($description);

        return [
            'source' => $sourceName,
            'sourceUrl' => $detailUrl,
            'externalId' => (string) ($candidate['externalId'] ?? ''),
            'title' => $title,
            'company' => '',
            'location' => '',
            'contractType' => $contractType,
            'workMode' => $workMode,
            'language' => (string) ($candidate['language'] ?? 'fr'),
            'description' => mb_substr($description, 0, 50_000),
            'publishedAt' => $this->publishedAt($xpath),
            'salaryMin' => null,
            'salaryMax' => null,
            'tjmMin' => $tjm['min'],
            'tjmMax' => $tjm['max'],
            'rawData' => [
                'extractionMethod' => 'DOM_DETAIL',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $detail
     * @return array<string, mixed>
     */
    private function merge(array $candidate, array $detail, string $method, string $detailUrl): array
    {
        $merged = $candidate;
        foreach ([
            'title', 'company', 'location', 'contractType', 'workMode', 'language', 'description',
            'publishedAt', 'salaryMin', 'salaryMax', 'tjmMin', 'tjmMax',
        ] as $key) {
            $value = $detail[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $merged[$key] = $value;
        }

        $merged['sourceUrl'] = $detailUrl;
        $originalRaw = is_array($candidate['rawData'] ?? null) ? $candidate['rawData'] : [];
        $detailRaw = is_array($detail['rawData'] ?? null) ? $detail['rawData'] : [];
        $merged['rawData'] = [
            ...$originalRaw,
            ...$detailRaw,
            'needsDetailFetch' => false,
            'detailEnriched' => true,
            'detailExtractionMethod' => $method,
            'detailUrl' => $detailUrl,
        ];

        return $merged;
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
            throw new \RuntimeException('La fiche offre ne contient pas un document HTML exploitable.');
        }

        return $document;
    }

    private function firstHeading(\DOMXPath $xpath): string
    {
        foreach (['//main//h1', '//article//h1', '//h1', '//main//h2', '//h2'] as $query) {
            $node = $xpath->query($query)?->item(0);
            $value = $this->clean($node?->textContent ?? '');
            if ($value !== '') {
                return mb_substr($value, 0, 240);
            }
        }

        return '';
    }

    private function visibleContent(\DOMDocument $document, \DOMXPath $xpath): string
    {
        $clone = clone $document;
        $cloneXpath = new \DOMXPath($clone);
        foreach (['//script', '//style', '//noscript', '//template', '//svg', '//nav', '//header', '//footer'] as $query) {
            $nodes = $cloneXpath->query($query);
            if (!$nodes instanceof \DOMNodeList) {
                continue;
            }
            for ($index = $nodes->length - 1; $index >= 0; --$index) {
                $node = $nodes->item($index);
                $node?->parentNode?->removeChild($node);
            }
        }

        $content = $cloneXpath->query('//main')?->item(0)
            ?? $cloneXpath->query('//article')?->item(0)
            ?? $cloneXpath->query('//body')?->item(0);

        return $this->clean($content?->textContent ?? $clone->textContent ?? '');
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

    private function publishedAt(\DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//time[@datetime]');
        if (!$nodes instanceof \DOMNodeList) {
            return null;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $value = trim($node->getAttribute('datetime'));
            if ($value === '') {
                continue;
            }
            try {
                return (new \DateTimeImmutable($value))->format(DATE_ATOM);
            } catch (\Exception) {
                continue;
            }
        }

        return null;
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function normalize(string $value): string
    {
        $value = $this->clean($value);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return strtolower($ascii === false ? $value : $ascii);
    }
}
