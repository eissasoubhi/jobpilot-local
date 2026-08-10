<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Browser;

final class BrowserWorkerConfiguration
{
    public function isConfigured(): bool
    {
        return $this->baseUrl() !== null && $this->token() !== null;
    }

    public function baseUrl(): ?string
    {
        $value = trim((string) ($_ENV['BROWSER_WORKER_URL'] ?? $_SERVER['BROWSER_WORKER_URL'] ?? getenv('BROWSER_WORKER_URL') ?: ''));
        if ($value === '') {
            return null;
        }

        $parts = parse_url($value);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new \RuntimeException('BROWSER_WORKER_URL est invalide.');
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';

        return $scheme.'://'.$host.$port.$path;
    }

    public function token(): ?string
    {
        $value = trim((string) ($_ENV['JOBPILOT_BROWSER_WORKER_TOKEN'] ?? $_SERVER['JOBPILOT_BROWSER_WORKER_TOKEN'] ?? getenv('JOBPILOT_BROWSER_WORKER_TOKEN') ?: ''));
        if ($value === '') {
            return null;
        }
        if (strlen($value) < 24) {
            throw new \RuntimeException('JOBPILOT_BROWSER_WORKER_TOKEN doit contenir au moins 24 caractères.');
        }

        return $value;
    }
}
