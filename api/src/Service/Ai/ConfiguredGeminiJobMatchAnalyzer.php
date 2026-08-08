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

        return $analyzer->analyze($job, $settings);
    }
}
