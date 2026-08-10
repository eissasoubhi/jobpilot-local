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

            return [];
        }

        if ($reservationId === null) {
            $this->logger->info('Custom scraper AI extraction skipped because the configured Gemini quota is exhausted.', [
                'model' => $model,
                'quota' => $config['quota'],
            ]);

            return [];
        }

        $offers = $extractor->extract($html, $pageUrl, $sourceName);
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
}
