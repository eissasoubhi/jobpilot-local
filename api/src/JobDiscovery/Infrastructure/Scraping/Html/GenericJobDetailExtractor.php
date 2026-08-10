<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Html;

use App\JobDiscovery\Application\JobTextMetadataExtractor;

final class GenericJobDetailExtractor
{
    private JobTextMetadataExtractor $textMetadata;

    public function __construct(
        private GenericJobListingExtractor $listingExtractor,
        ?JobTextMetadataExtractor $textMetadata = null,
    ) {
        $this->textMetadata = $textMetadata ?? new JobTextMetadataExtractor();
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
        $metadata = $this->textMetadata->extract($description);

        return [
            'source' => $sourceName,
            'sourceUrl' => $detailUrl,
            'externalId' => (string) ($candidate['externalId'] ?? ''),
            'title' => $title,
            'company' => '',
            'location' => '',
            'contractType' => $metadata['contractType'],
            'workMode' => $metadata['workMode'],
            'language' => (string) ($candidate['language'] ?? 'fr'),
            'description' => mb_substr($description, 0, 50_000),
            'publishedAt' => $this->publishedAt($xpath),
            'salaryMin' => null,
            'salaryMax' => null,
            'tjmMin' => $metadata['tjmMin'],
            'tjmMax' => $metadata['tjmMax'],
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
