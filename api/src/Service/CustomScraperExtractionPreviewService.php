<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericDomCompactor;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericHtmlModeDetector;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingResult;
use App\Service\Ai\DomOfferExtractorInterface;

final class CustomScraperExtractionPreviewService
{
    public function __construct(
        private CustomScraperHttpPageFetcher $fetcher,
        private GenericHtmlModeDetector $modeDetector,
        private GenericDomCompactor $domCompactor,
        private DomOfferExtractorInterface $extractor,
    ) {
    }

    /** @return array<string, mixed> */
    public function preview(CustomScraperSource $source): array
    {
        $data = $source->toArray();
        $configuredMode = (string) ($data['mode'] ?? CustomScraperSource::MODE_AUTO);
        $result = $this->fetcher->fetchListing($source);
        $modeAnalysis = $this->modeDetector->analyze($result->body);
        $recommendedMode = (string) $modeAnalysis['recommendedMode'];
        $effectiveMode = $configuredMode === CustomScraperSource::MODE_AUTO
            ? $recommendedMode
            : $configuredMode;

        $base = [
            'configuredMode' => $configuredMode,
            'recommendedMode' => $recommendedMode,
            'effectiveMode' => $effectiveMode,
            'requiresBrowser' => $effectiveMode === CustomScraperSource::MODE_BROWSER,
            'modeConfidence' => $modeAnalysis['confidence'],
            'modeReason' => $modeAnalysis['reason'],
            'http' => $this->httpMetadata($result, (string) ($data['listingUrl'] ?? '')),
        ];

        if ($effectiveMode === CustomScraperSource::MODE_BROWSER) {
            return [
                ...$base,
                'aiCalled' => false,
                'ai' => null,
                'dom' => null,
                'offers' => [],
                'message' => 'Cette source nécessite le rendu Browser/Playwright avant l’analyse Gemini. Aucun quota Gemini n’a été consommé.',
            ];
        }

        $dom = $this->domCompactor->compact($result->body);
        $extraction = $this->extractor->extract(
            (string) ($data['name'] ?? $data['domain'] ?? 'Source personnalisée'),
            (string) ($data['domain'] ?? ''),
            $result->url,
            $dom['content'],
        );

        return [
            ...$base,
            'aiCalled' => true,
            'ai' => [
                'model' => $extraction['model'],
                'cacheHit' => $extraction['cacheHit'],
                'confidence' => $extraction['confidence'],
                'notes' => $extraction['notes'],
            ],
            'dom' => [
                'originalBytes' => $dom['originalBytes'],
                'compactedCharacters' => $dom['compactedCharacters'],
                'truncated' => $dom['truncated'],
                'structuredDataBlocks' => $dom['structuredDataBlocks'],
            ],
            'offers' => $extraction['offers'],
            'message' => sprintf('%d offre(s) détectée(s) dans l’aperçu Gemini.', count($extraction['offers'])),
        ];
    }

    /** @return array<string, mixed> */
    private function httpMetadata(HttpScrapingResult $result, string $requestedUrl): array
    {
        return [
            'requestedUrl' => $requestedUrl,
            'finalUrl' => $result->url,
            'statusCode' => $result->statusCode,
            'contentType' => $this->firstHeader($result->headers, 'content-type'),
            'responseBytes' => strlen($result->body),
            'networkRequests' => $result->attempts,
            'fromCache' => $result->fromCache,
        ];
    }

    /** @param array<string, list<string>> $headers */
    private function firstHeader(array $headers, string $name): ?string
    {
        $values = $headers[strtolower($name)] ?? null;
        if (!is_array($values) || !isset($values[0])) {
            return null;
        }

        $value = trim((string) $values[0]);

        return $value === '' ? null : $value;
    }
}
