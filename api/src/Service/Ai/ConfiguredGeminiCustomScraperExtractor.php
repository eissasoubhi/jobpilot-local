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
        $config = $this->configuration->load();
        if (!($config['enabled'] ?? false)
            || trim((string) ($config['apiKey'] ?? '')) === ''
            || trim((string) ($config['model'] ?? '')) === '') {
            return [];
        }

        $model = trim((string) $config['model']);
        $extractor = new GeminiCustomScraperExtractor(
            $this->httpClient,
            $this->logger,
            $this->contextBuilder,
            true,
            (string) $config['apiKey'],
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

        $estimatedTokens = $extractor->estimatedInputTokens($html, $pageUrl, $sourceName, $this->quotaManager);
        $quota = is_array($config['quota'] ?? null) ? $config['quota'] : [];
        $reservation = $this->quotaManager->reserve(
            self::PROVIDER,
            $model,
            $estimatedTokens,
            max(1, (int) ($quota['rpm'] ?? 1)),
            max(1, (int) ($quota['tpm'] ?? 1)),
            max(1, (int) ($quota['rpd'] ?? 1)),
            max(1, min(100, (int) ($quota['usablePercent'] ?? 80))),
        );
        if ($reservation === null) {
            $this->logger->info('Custom scraper AI extraction skipped because the configured Gemini quota is exhausted.', [
                'model' => $model,
            ]);

            return [];
        }

        $offers = $extractor->extract($html, $pageUrl, $sourceName);
        try {
            $this->quotaManager->reconcile(
                self::PROVIDER,
                $model,
                $reservation,
                $extractor->lastInputTokens(),
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Custom scraper AI quota reconciliation failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
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
