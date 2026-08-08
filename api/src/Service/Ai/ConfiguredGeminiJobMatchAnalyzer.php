<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ConfiguredGeminiJobMatchAnalyzer implements AiJobMatchAnalyzerInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private AiMatchingConfigurationStore $configuration,
        private AiQuotaManager $quotaManager,
        private AiMatchingCache $cache,
    ) {
    }

    public function analyze(JobOffer $job, UserSettings $settings): ?AiJobMatchAnalysis
    {
        $configuration = $this->configuration->effective();
        $analyzer = new GeminiJobMatchAnalyzer(
            $this->httpClient,
            $this->logger,
            $configuration['enabled'],
            $configuration['apiKey'],
            $configuration['model'],
        );

        if (!$configuration['enabled'] || trim($configuration['apiKey']) === '') {
            return $analyzer->analyze($job, $settings);
        }

        $fingerprint = null;
        try {
            $fingerprint = $analyzer->cacheFingerprint($job, $settings);
            $cached = $this->cache->get('gemini', $configuration['model'], $fingerprint);
            if ($cached !== null) {
                $this->logger->debug('Gemini matching cache hit; provider call and quota reservation skipped.', [
                    'model' => $configuration['model'],
                ]);

                return $cached;
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Gemini matching cache lookup failed; continuing without cache.', [
                'model' => $configuration['model'],
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        try {
            $reservationId = $this->quotaManager->reserve(
                'gemini',
                $configuration['model'],
                $analyzer->estimatedInputTokens($job, $settings, $this->quotaManager),
                $configuration['quota'],
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Gemini matching skipped because local quota accounting failed.', [
                'model' => $configuration['model'],
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($reservationId === null) {
            $this->logger->notice('Gemini matching skipped because the local quota guard reached its safe limit.', [
                'model' => $configuration['model'],
                'quota' => $configuration['quota'],
            ]);

            return null;
        }

        $analysis = $analyzer->analyze($job, $settings);
        $actualInputTokens = $analyzer->lastInputTokens();
        if ($actualInputTokens !== null) {
            try {
                $this->quotaManager->reconcile($reservationId, $actualInputTokens);
            } catch (\Throwable $exception) {
                $this->logger->warning('Gemini quota reconciliation failed after the matching response.', [
                    'model' => $configuration['model'],
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($analysis !== null && $fingerprint !== null) {
            try {
                $this->cache->put('gemini', $configuration['model'], $fingerprint, $analysis);
            } catch (\Throwable $exception) {
                $this->logger->warning('Gemini matching result could not be cached.', [
                    'model' => $configuration['model'],
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $analysis;
    }
}
