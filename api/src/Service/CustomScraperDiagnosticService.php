<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Infrastructure\Scraping\Html\GenericHtmlModeDetector;
use App\JobDiscovery\Infrastructure\Scraping\Http\ControlledHttpScrapingClient;
use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingRequest;
use App\JobDiscovery\Infrastructure\Scraping\Http\RobotsTxtGuard;

final class CustomScraperDiagnosticService
{
    public function __construct(
        private ControlledHttpScrapingClient $httpClient,
        private GenericHtmlModeDetector $modeDetector,
        private RobotsTxtGuard $robotsTxtGuard,
    ) {
    }

    /** @return array<string, mixed> */
    public function diagnose(CustomScraperSource $source): array
    {
        $data = $source->toArray();
        if (($data['authorizationConfirmed'] ?? false) !== true) {
            throw new \InvalidArgumentException('L’autorisation de collecte doit être confirmée avant de tester ce site.');
        }

        $listingUrl = (string) ($data['listingUrl'] ?? '');
        $domain = (string) ($data['domain'] ?? '');
        $configuredMode = (string) ($data['mode'] ?? CustomScraperSource::MODE_AUTO);
        $checkedAt = is_string($data['authorizationCheckedAt'] ?? null)
            ? new \DateTimeImmutable((string) $data['authorizationCheckedAt'])
            : new \DateTimeImmutable('today');

        $policy = new ConnectorPolicy(
            ConnectorComplianceStatus::ALLOWED,
            $checkedAt,
            is_string($data['authorizationReference'] ?? null) ? $data['authorizationReference'] : 'Autorisation confirmée par l’utilisateur.',
            maxRequestsPerSync: 1,
            dailyQuota: 10,
            minimumDelayMilliseconds: 1_000,
            respectsRobotsTxt: true,
        );

        $request = new HttpScrapingRequest(
            'custom-'.substr(hash('sha256', $domain.'|'.$listingUrl), 0, 16),
            $listingUrl,
            $policy,
            timeoutSeconds: 10,
            maxRetries: 0,
            initialBackoffMilliseconds: 0,
            maxResponseBytes: 3_000_000,
        );

        // Run the check explicitly so the UI can explain canonical redirects.
        // ControlledHttpScrapingClient performs the same guard again, which reuses
        // the robots cache and therefore does not add another network request.
        $robots = $this->robotsTxtGuard->assertAllowed($listingUrl, $request->userAgent);
        $result = $this->httpClient->fetch($request);

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
            'robots' => $robots->toArray(),
            'http' => [
                'requestedUrl' => $listingUrl,
                'finalUrl' => $result->url,
                'statusCode' => $result->statusCode,
                'contentType' => $this->firstHeader($result->headers, 'content-type'),
                'responseBytes' => strlen($result->body),
                'networkRequests' => $result->attempts,
                'fromCache' => $result->fromCache,
            ],
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
