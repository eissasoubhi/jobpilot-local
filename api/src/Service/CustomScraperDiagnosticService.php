<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericHtmlModeDetector;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingResult;

final class CustomScraperDiagnosticService
{
    public function __construct(
        private CustomScraperHttpPageFetcher $fetcher,
        private GenericHtmlModeDetector $modeDetector,
    ) {
    }

    /** @return array<string, mixed> */
    public function diagnose(CustomScraperSource $source): array
    {
        $data = $source->toArray();
        $configuredMode = (string) ($data['mode'] ?? CustomScraperSource::MODE_AUTO);
        $result = $this->fetcher->fetchListing($source);
        $analysis = $this->modeDetector->analyze($result->body);
        $recommendedMode = (string) $analysis['recommendedMode'];
        $effectiveMode = $configuredMode === CustomScraperSource::MODE_AUTO
            ? $recommendedMode
            : $configuredMode;

        return [
            'configuredMode' => $configuredMode,
            'recommendedMode' => $recommendedMode,
            'effectiveMode' => $effectiveMode,
            'confidence' => $analysis['confidence'],
            'reason' => $analysis['reason'],
            'browserVerificationRequired' => $analysis['browserVerificationRequired'],
            'signals' => $analysis['signals'],
            'http' => $this->httpMetadata($result, (string) ($data['listingUrl'] ?? '')),
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
