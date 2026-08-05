<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Monitoring;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ConnectorAlertWebhookNotifier
{
    public const RESULT_DISABLED = 'DISABLED';
    public const RESULT_NO_ALERT = 'NO_ALERT';
    public const RESULT_UNCHANGED = 'UNCHANGED';
    public const RESULT_SENT = 'SENT';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $stateFile,
        private string $webhookUrl,
        private string $allowedHost,
        private string $signingSecret,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $reports
     */
    public function notify(array $reports, int $intervalSeconds): string
    {
        $alerts = $this->normalizeAlerts($reports);
        if ($alerts === []) {
            $this->clearState();

            return self::RESULT_NO_ALERT;
        }

        $webhookUrl = trim($this->webhookUrl);
        if ($webhookUrl === '') {
            return self::RESULT_DISABLED;
        }

        $this->validateEndpoint($webhookUrl);
        $fingerprint = $this->fingerprint($alerts);
        if ($this->readFingerprint() === $fingerprint) {
            return self::RESULT_UNCHANGED;
        }

        $payload = [
            'event' => 'connector.freshness.alert',
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'intervalSeconds' => max(900, $intervalSeconds),
            'alertCount' => count($alerts),
            'connectors' => $alerts,
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'JobPilot-Connector-Monitor/1.0',
        ];
        if ($this->signingSecret !== '') {
            $headers['X-JobPilot-Signature'] = 'sha256='.hash_hmac('sha256', $json, $this->signingSecret);
        }

        $response = $this->httpClient->request('POST', $webhookUrl, [
            'headers' => $headers,
            'body' => $json,
            'timeout' => 5.0,
            'max_redirects' => 0,
        ]);
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf('Connector alert webhook returned HTTP %d.', $statusCode));
        }

        $this->writeFingerprint($fingerprint);

        return self::RESULT_SENT;
    }

    /**
     * @param list<array<string, mixed>> $reports
     *
     * @return list<array{
     *   code: string,
     *   name: string,
     *   status: string,
     *   lastSyncedAt: string|null,
     *   nextExpectedAt: string|null,
     *   overdueBySeconds: int,
     *   reason: string
     * }>
     */
    private function normalizeAlerts(array $reports): array
    {
        $alerts = [];

        foreach ($reports as $report) {
            if (($report['alert'] ?? false) !== true) {
                continue;
            }

            $alerts[] = [
                'code' => (string) ($report['code'] ?? 'unknown'),
                'name' => (string) ($report['name'] ?? 'Unknown connector'),
                'status' => (string) ($report['status'] ?? 'UNKNOWN'),
                'lastSyncedAt' => is_string($report['lastSyncedAt'] ?? null) ? $report['lastSyncedAt'] : null,
                'nextExpectedAt' => is_string($report['nextExpectedAt'] ?? null) ? $report['nextExpectedAt'] : null,
                'overdueBySeconds' => max(0, (int) ($report['overdueBySeconds'] ?? 0)),
                'reason' => (string) ($report['reason'] ?? 'Connector freshness requires attention.'),
            ];
        }

        usort(
            $alerts,
            static fn (array $left, array $right): int => $left['code'] <=> $right['code'],
        );

        return $alerts;
    }

    /**
     * @param list<array<string, mixed>> $alerts
     */
    private function fingerprint(array $alerts): string
    {
        $state = array_map(
            static fn (array $alert): array => [
                'code' => $alert['code'],
                'status' => $alert['status'],
            ],
            $alerts,
        );

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    private function validateEndpoint(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new \InvalidArgumentException('Connector alert webhook URL is invalid.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $allowedHost = strtolower(rtrim(trim($this->allowedHost), '.'));

        if ($scheme !== 'https' || $host === '') {
            throw new \InvalidArgumentException('Connector alert webhook must use an absolute HTTPS URL.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('Connector alert webhook URL must not contain credentials.');
        }
        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            throw new \InvalidArgumentException('Connector alert webhook only allows the standard HTTPS port.');
        }
        if ($allowedHost === '' || $host !== $allowedHost) {
            throw new \InvalidArgumentException('Connector alert webhook host is not explicitly allowlisted.');
        }
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            throw new \InvalidArgumentException('Connector alert webhook host must be a public DNS hostname.');
        }
    }

    private function readFingerprint(): ?string
    {
        if (!is_file($this->stateFile)) {
            return null;
        }

        $content = @file_get_contents($this->stateFile);
        if (!is_string($content) || $content === '') {
            return null;
        }

        try {
            $state = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($state) && is_string($state['fingerprint'] ?? null)
            ? $state['fingerprint']
            : null;
    }

    private function writeFingerprint(string $fingerprint): void
    {
        $directory = dirname($this->stateFile);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create connector alert state directory "%s".', $directory));
        }

        $temporaryFile = tempnam($directory, 'connector-alert-');
        if ($temporaryFile === false) {
            throw new \RuntimeException('Unable to create connector alert state file.');
        }

        try {
            $content = json_encode([
                'fingerprint' => $fingerprint,
                'sentAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            if (file_put_contents($temporaryFile, $content, LOCK_EX) === false) {
                throw new \RuntimeException('Unable to write connector alert state file.');
            }
            @chmod($temporaryFile, 0600);
            if (!rename($temporaryFile, $this->stateFile)) {
                throw new \RuntimeException('Unable to replace connector alert state file.');
            }
        } finally {
            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    private function clearState(): void
    {
        if (is_file($this->stateFile)) {
            @unlink($this->stateFile);
        }
    }
}
