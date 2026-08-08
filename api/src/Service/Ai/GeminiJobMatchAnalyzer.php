<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeminiJobMatchAnalyzer implements AiJobMatchAnalyzerInterface
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    private const RESPONSE_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'score' => [
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 100,
                'description' => 'Overall semantic fit score between the candidate profile and this job.',
            ],
            'confidence' => [
                'type' => 'number',
                'minimum' => 0,
                'maximum' => 1,
                'description' => 'Confidence in the assessment, from 0 to 1.',
            ],
            'decision' => [
                'type' => 'string',
                'enum' => ['MATCH', 'REVIEW', 'NO_MATCH'],
                'description' => 'MATCH for a strong fit, REVIEW for an ambiguous or partial fit, NO_MATCH for a clear mismatch.',
            ],
            'primaryRole' => [
                'type' => 'string',
                'description' => 'The main role actually requested by the job description.',
            ],
            'primaryStack' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Technologies that are genuinely core to the requested role.',
            ],
            'secondaryStack' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Secondary, nice-to-have, legacy or contextual technologies.',
            ],
            'mustHaves' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Mandatory or strongly required requirements.',
            ],
            'niceToHaves' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Optional or secondary requirements.',
            ],
            'missingMustHaves' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Important mandatory requirements not supported by the candidate profile.',
            ],
            'conflicts' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Strong contradictions between the requested role and candidate profile.',
            ],
            'explanation' => [
                'type' => 'string',
                'description' => 'Short explanation of the score, emphasizing the primary role and stack rather than incidental keywords.',
            ],
        ],
        'required' => [
            'score',
            'confidence',
            'decision',
            'primaryRole',
            'primaryStack',
            'secondaryStack',
            'mustHaves',
            'niceToHaves',
            'missingMustHaves',
            'conflicts',
            'explanation',
        ],
        'additionalProperties' => false,
    ];

    private ?int $lastInputTokens = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled,
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public function analyze(JobOffer $job, UserSettings $settings): ?AiJobMatchAnalysis
    {
        $this->lastInputTokens = null;

        if (!$this->enabled || trim($this->apiKey) === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'headers' => [
                    'x-goog-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'input' => $this->buildPrompt($job, $settings),
                    'response_format' => [
                        'type' => 'text',
                        'mime_type' => 'application/json',
                        'schema' => self::RESPONSE_SCHEMA,
                    ],
                ],
                'timeout' => 20,
            ]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                $this->logger->warning('Gemini matching request failed; deterministic matching will be used.', [
                    'status' => $status,
                    'model' => $this->model,
                ]);

                return null;
            }

            $payload = $response->toArray(false);
            $usage = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];
            if (is_numeric($usage['total_input_tokens'] ?? null)) {
                $this->lastInputTokens = max(1, (int) $usage['total_input_tokens']);
            }

            $text = $this->extractOutputText($payload);
            if ($text === null || trim($text) === '') {
                $this->logger->warning('Gemini matching response did not contain a model text output.', [
                    'model' => $this->model,
                ]);

                return null;
            }

            $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                return null;
            }

            return AiJobMatchAnalysis::fromArray($data);
        } catch (ExceptionInterface|\JsonException|\InvalidArgumentException $exception) {
            $this->logger->warning('Gemini matching analysis failed; deterministic matching will be used.', [
                'model' => $this->model,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function estimatedInputTokens(JobOffer $job, UserSettings $settings, AiQuotaManager $quotaManager): int
    {
        return $quotaManager->estimateTextInputTokens($this->buildPrompt($job, $settings));
    }

    public function lastInputTokens(): ?int
    {
        return $this->lastInputTokens;
    }

    private function buildPrompt(JobOffer $job, UserSettings $settings): string
    {
        $candidate = [
            'targetJobs' => $settings->getTargetJobs(),
            'skills' => $settings->getSkills(),
            'exclusions' => $settings->getExclusions(),
        ];

        $offer = [
            'title' => $job->getTitle(),
            'description' => $job->getDescription(),
            'contractType' => $job->getContractType(),
            'location' => $job->getLocation(),
            'workMode' => $job->getWorkMode(),
        ];

        $prompt = <<<'PROMPT'
You are the semantic job matching engine for JobPilot.

Assess whether the job genuinely fits the candidate profile. Read the complete job description and identify the primary requested role and stack before assigning a score. Do not award a high score merely because generic words such as backend, web, API or developer overlap.

Distinguish core/must-have technologies from secondary, nice-to-have, legacy or contextual mentions. Treat explicit alternatives such as "Java or PHP" as real alternatives when the description presents them as equivalent options. Strongly reduce the score when the primary requested stack conflicts with the candidate profile, even if the description contains incidental matching keywords.

Use only the candidate matching criteria supplied below. Do not infer experience or skills that are not present. Return a concise structured assessment matching the required JSON schema.

Candidate matching criteria:
__CANDIDATE__

Job offer:
__OFFER__
PROMPT;

        return str_replace(
            ['__CANDIDATE__', '__OFFER__'],
            [
                json_encode($candidate, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($offer, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
            $prompt,
        );
    }

    /** @param array<string, mixed> $payload */
    private function extractOutputText(array $payload): ?string
    {
        $steps = $payload['steps'] ?? null;
        if (is_array($steps)) {
            for ($index = count($steps) - 1; $index >= 0; --$index) {
                $step = $steps[$index] ?? null;
                if (!is_array($step) || ($step['type'] ?? null) !== 'model_output') {
                    continue;
                }
                $content = $step['content'] ?? null;
                if (!is_array($content)) {
                    continue;
                }
                for ($contentIndex = count($content) - 1; $contentIndex >= 0; --$contentIndex) {
                    $item = $content[$contentIndex] ?? null;
                    if (is_array($item) && ($item['type'] ?? null) === 'text' && is_string($item['text'] ?? null)) {
                        return $item['text'];
                    }
                }
            }
        }

        $outputs = $payload['outputs'] ?? null;
        if (is_array($outputs)) {
            for ($index = count($outputs) - 1; $index >= 0; --$index) {
                $output = $outputs[$index] ?? null;
                if (is_array($output) && ($output['type'] ?? null) === 'text' && is_string($output['text'] ?? null)) {
                    return $output['text'];
                }
            }
        }

        return null;
    }
}
