<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Http;

final readonly class RobotsTxtCheckResult
{
    public function __construct(
        public string $requestedUrl,
        public string $finalUrl,
        public int $statusCode,
        public int $redirects,
        public bool $fromCache = false,
    ) {
    }

    /** @return array{requestedUrl: string, finalUrl: string, statusCode: int, redirects: int, fromCache: bool} */
    public function toArray(): array
    {
        return [
            'requestedUrl' => $this->requestedUrl,
            'finalUrl' => $this->finalUrl,
            'statusCode' => $this->statusCode,
            'redirects' => $this->redirects,
            'fromCache' => $this->fromCache,
        ];
    }
}
