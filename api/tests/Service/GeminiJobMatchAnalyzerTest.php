<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;
use App\Service\Ai\GeminiJobMatchAnalyzer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GeminiJobMatchAnalyzerTest extends TestCase
{
    public function testDisabledAnalyzerDoesNotCallGemini(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            self::fail('Gemini must not be called while AI matching is disabled.');
        });
        $analyzer = new GeminiJobMatchAnalyzer(
            $client,
            new NullLogger(),
            false,
            'test-key',
            'gemini-3.5-flash-lite',
        );

        self::assertNull($analyzer->analyze($this->job(), $this->settings()));
        self::assertSame(0, $client->getRequestsCount());
    }

    public function testParsesStructuredInteractionResponse(): void
    {
        $requestBody = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestBody): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://generativelanguage.googleapis.com/v1beta/interactions', $url);
            $requestBody = json_decode((string) ($options['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);

            return new MockResponse(json_encode([
                'id' => 'int_test',
                'usage' => [
                    'total_input_tokens' => 321,
                    'total_output_tokens' => 80,
                    'total_tokens' => 401,
                ],
                'steps' => [[
                    'type' => 'model_output',
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode([
                            'score' => 24,
                            'confidence' => 0.96,
                            'decision' => 'NO_MATCH',
                            'primaryRole' => 'Senior Java Backend Developer',
                            'primaryStack' => ['Java', 'Spring Boot'],
                            'secondaryStack' => ['PHP'],
                            'phpRelevance' => 'CONTEXTUAL',
                            'mustHaves' => ['Java', 'Spring Boot'],
                            'niceToHaves' => ['PHP'],
                            'missingMustHaves' => ['Java', 'Spring Boot'],
                            'conflicts' => ['Primary stack is Java/Spring while the candidate targets PHP/Symfony.'],
                            'explanation' => 'PHP is only mentioned as legacy context.',
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ], JSON_THROW_ON_ERROR));
        });
        $analyzer = new GeminiJobMatchAnalyzer(
            $client,
            new NullLogger(),
            true,
            'test-key',
            'gemini-3.5-flash-lite',
        );

        $analysis = $analyzer->analyze($this->job(), $this->settings());

        self::assertNotNull($analysis);
        self::assertSame(24, $analysis->score);
        self::assertSame('NO_MATCH', $analysis->decision);
        self::assertSame(['Java', 'Spring Boot'], $analysis->primaryStack);
        self::assertSame(['PHP'], $analysis->secondaryStack);
        self::assertSame('CONTEXTUAL', $analysis->phpRelevance);
        self::assertSame(321, $analyzer->lastInputTokens());
        self::assertIsArray($requestBody);
        self::assertSame('gemini-3.5-flash-lite', $requestBody['model']);
        self::assertSame('application/json', $requestBody['response_format']['mime_type']);
        self::assertSame('object', $requestBody['response_format']['schema']['type']);
        self::assertContains('phpRelevance', $requestBody['response_format']['schema']['required']);
        self::assertSame(
            ['PRIMARY', 'ALTERNATIVE', 'MIXED_REQUIRED', 'SECONDARY', 'CONTEXTUAL', 'ABSENT', 'UNCLEAR'],
            $requestBody['response_format']['schema']['properties']['phpRelevance']['enum'],
        );
        self::assertStringContainsString('Senior PHP Symfony Developer', $requestBody['input']);
        self::assertStringContainsString('Senior Backend Java Developer', $requestBody['input']);
        self::assertStringContainsString('distinguish a genuine PHP profile from other profiles', $requestBody['input']);
        self::assertStringContainsString('Do not treat every non-PHP job as a mismatch automatically', $requestBody['input']);
    }

    public function testInvalidAiPayloadFallsBackToNull(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'steps' => [[
                'type' => 'model_output',
                'content' => [[
                    'type' => 'text',
                    'text' => '{"score":95}',
                ]],
            ]],
        ], JSON_THROW_ON_ERROR)));
        $analyzer = new GeminiJobMatchAnalyzer(
            $client,
            new NullLogger(),
            true,
            'test-key',
            'gemini-3.5-flash-lite',
        );

        self::assertNull($analyzer->analyze($this->job(), $this->settings()));
    }

    private function job(): JobOffer
    {
        return (new JobOffer())->fill([
            'title' => 'Senior Backend Java Developer',
            'description' => 'Primary stack Java Spring Boot. PHP is mentioned only for a legacy migration.',
            'company' => 'Example',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'workMode' => 'Hybrid',
        ]);
    }

    private function settings(): UserSettings
    {
        return (new UserSettings())->fill([
            'targetJobs' => ['Senior PHP Symfony Developer'],
            'skills' => ['PHP', 'Symfony', 'React'],
            'exclusions' => ['Stage'],
        ]);
    }
}
