<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Http;

use App\JobDiscovery\Domain\Connector\ConnectorPolicy;

final readonly class HttpScrapingRequest
{
    public function __construct(
        public string $connectorCode,
        public string $url,
        public ConnectorPolicy $policy,
        public int $timeoutSeconds = 10,
        public int $maxRetries = 2,
        public int $initialBackoffMilliseconds = 250,
        public int $maxResponseBytes = 2_000_000,
        public string $userAgent = 'JobPilot/0.2 (controlled job discovery; +https://github.com/eissasoubhi/jobpilot-local)',
    ) {
        if (trim($this->connectorCode) === '') {
            throw new \InvalidArgumentException('Le code du connecteur est obligatoire.');
        }
        if (filter_var($this->url, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('L’URL de scraping est invalide.');
        }
        if ($this->timeoutSeconds < 1 || $this->timeoutSeconds > 30) {
            throw new \InvalidArgumentException('Le timeout HTTP doit être compris entre 1 et 30 secondes.');
        }
        if ($this->maxRetries < 0 || $this->maxRetries > 4) {
            throw new \InvalidArgumentException('Le nombre de retries doit être compris entre 0 et 4.');
        }
        if ($this->initialBackoffMilliseconds < 0 || $this->initialBackoffMilliseconds > 10_000) {
            throw new \InvalidArgumentException('Le backoff initial est invalide.');
        }
        if ($this->maxResponseBytes < 1_024 || $this->maxResponseBytes > 10_000_000) {
            throw new \InvalidArgumentException('La taille maximale de réponse est invalide.');
        }
        if (trim($this->userAgent) === '') {
            throw new \InvalidArgumentException('Un User-Agent explicite est obligatoire.');
        }
    }
}
