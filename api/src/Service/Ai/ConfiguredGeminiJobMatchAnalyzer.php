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

        $reservationId = $this->quotaManager->reserve(
            'gemini',
            $configuration['model'],
            $analyzer->estimatedInputTokens($job, $settings, $this->quotaManager),
            $configuration['quota'],
        );

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
            $this->quotaManager->reconcile($reservationId, $actualInputTokens);
        }

        return $analysis;
    }
}
