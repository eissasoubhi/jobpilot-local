<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Ai\AiJobMatchAnalysis;
use App\Service\Ai\AiMatchingCache;
use PHPUnit\Framework\TestCase;

final class AiMatchingCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-ai-cache-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testStoresAndRestoresAValidStructuredAnalysis(): void
    {
        $cache = new AiMatchingCache($this->directory);
        $analysis = $this->analysis();

        self::assertNull($cache->get('gemini', 'model-a', 'fingerprint-a'));

        $cache->put('gemini', 'model-a', 'fingerprint-a', $analysis);
        $cached = $cache->get('gemini', 'model-a', 'fingerprint-a');

        self::assertNotNull($cached);
        self::assertSame($analysis->toArray(), $cached->toArray());
    }

    public function testProviderModelAndInputFingerprintArePartOfTheCacheIdentity(): void
    {
        $cache = new AiMatchingCache($this->directory);
        $cache->put('gemini', 'model-a', 'fingerprint-a', $this->analysis());

        self::assertNotNull($cache->get('gemini', 'model-a', 'fingerprint-a'));
        self::assertNull($cache->get('gemini', 'model-b', 'fingerprint-a'));
        self::assertNull($cache->get('openai', 'model-a', 'fingerprint-a'));
        self::assertNull($cache->get('gemini', 'model-a', 'fingerprint-b'));
    }

    public function testCacheFileDoesNotContainProviderCredentialsOrPromptInputs(): void
    {
        $cache = new AiMatchingCache($this->directory);
        $cache->put('gemini', 'model-a', hash('sha256', 'private prompt input'), $this->analysis());

        $persisted = (string) file_get_contents($this->directory.'/ai-matching-cache.json');

        self::assertStringNotContainsString('private prompt input', $persisted);
        self::assertStringNotContainsString('api-key', $persisted);
    }

    private function analysis(): AiJobMatchAnalysis
    {
        return new AiJobMatchAnalysis(
            88,
            0.94,
            'MATCH',
            'Senior PHP Symfony Developer',
            ['PHP', 'Symfony'],
            ['React'],
            ['PHP', 'Symfony'],
            ['React'],
            [],
            [],
            'The primary role and stack fit the configured profile.',
        );
    }
}
