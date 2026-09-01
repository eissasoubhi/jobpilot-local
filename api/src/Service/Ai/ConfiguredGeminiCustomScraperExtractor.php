<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\JobDiscovery\Application\CustomScraperAiExtractorInterface;
use App\JobDiscovery\Infrastructure\Scraping\Html\CustomScraperAiPageContextBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ConfiguredGeminiCustomScraperExtractor implements CustomScraperAiExtractorInterface
{
    private const PROVIDER = 'gemini';

    public function __construct(
        private AiMatchingConfigurationStore $configuration,
        private AiQuotaManager $quotaManager,
        private CustomScraperAiExtractionCache $cache,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private CustomScraperAiPageContextBuilder $contextBuilder,
        private ?AiUsageLedger $usageLedger = null,
    ) {
    }

    public function extract(string $html, string $pageUrl, string $sourceName): array
    {
        $config = $this->configuration->effective();
        if (!$config['enabled']
            || trim($config['apiKey']) === ''
            || trim($config['model']) === '') {
            return [];
        }

        $model = trim($config['model']);
        $extractor = new GeminiCustomScraperExtractor(
            $this->httpClient,
            $this->logger,
            $this->contextBuilder,
            true,
            $config['apiKey'],
            $model,
        );
        $fingerprint = $extractor->cacheFingerprint($html, $pageUrl, $sourceName);

        try {
            $cached = $this->cache->get(self::PROVIDER, $model, $fingerprint);
            if ($cached !== null) {
                $this->recordSafely($model, 'cache_hit', [], null, $sourceName);

                return $cached;
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Custom scraper AI cache read failed; continuing without cache.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        try {
            $reservationId = $this->quotaManager->reserve(
                self::PROVIDER,
                $model,
                $extractor->estimatedInputTokens($html, $pageUrl, $sourceName, $this->quotaManager),
                $config['quota'],
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Custom scraper Gemini extraction skipped because local quota accounting failed.', [
                'model' => $model,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->recordSafely($model, 'quota_error', [], null, $sourceName, null, $exception::class);

            return [];
        }

        if ($reservationId === null) {
            $this->logger->info('Custom scraper AI extraction skipped because the configured Gemini quota is exhausted.', [
                'model' => $model,
                'quota' => $config['quota'],
            ]);
            $this->recordSafely($model, 'quota_blocked', [], null, $sourceName);

            return [];
        }

        $startedAt = hrtime(true);
        $offers = $extractor->extract($html, $pageUrl, $sourceName);
        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $actualInputTokens = $extractor->lastInputTokens();
        if ($actualInputTokens !== null) {
            try {
                $this->quotaManager->reconcile($reservationId, $actualInputTokens);
            } catch (\Throwable $exception) {
                $this->logger->warning('Custom scraper AI quota reconciliation failed.', [
                    'model' => $model,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->recordSafely(
            $model,
            $extractor->lastFailure() === null ? 'provider_success' : 'provider_failure',
            $extractor->lastUsage(),
            $latencyMs,
            $sourceName,
            $extractor->lastStatusCode(),
            $extractor->lastFailure(),
        );

        if ($offers !== []) {
            try {
                $this->cache->put(self::PROVIDER, $model, $fingerprint, $offers);
            } catch (\Throwable $exception) {
                $this->logger->warning('Custom scraper AI cache write failed.', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $offers;
    }

    /** @param array<string, mixed> $usage */
    private function recordSafely(
        string $model,
        string $outcome,
        array $usage,
        ?int $latencyMs,
        string $sourceName,
        ?int $httpStatus = null,
        ?string $errorClass = null,
    ): void {
        if ($this->usageLedger === null) {
            return;
        }

        try {
            $this->usageLedger->record(
                self::PROVIDER,
                $model,
                'custom_scraper_extraction',
                $outcome,
                $usage,
                $latencyMs,
                'connector_source',
                $sourceName,
                $httpStatus,
                $errorClass,
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('AI usage telemetry could not be recorded.', [
                'purpose' => 'custom_scraper_extraction',
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
