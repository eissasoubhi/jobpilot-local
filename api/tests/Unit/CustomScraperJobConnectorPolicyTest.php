<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CustomScraperSource;
use App\JobDiscovery\Infrastructure\CustomScraping\CustomScraperJobConnector;
use App\Service\CustomScraperExtractionService;
use PHPUnit\Framework\TestCase;

final class CustomScraperJobConnectorPolicyTest extends TestCase
{
    public function testKeywordSearchPolicyUsesTheSameGlobalListingCapAsRuntime(): void
    {
        $source = $this->source([
            'searchUrlTemplate' => 'https://jobs.example.com/search?q={keyword}',
            'searchKeywords' => ['PHP', 'Symfony', 'Vue.js', 'React.js'],
            'maxPages' => 5,
            'maxDetails' => 20,
        ]);

        $policy = $this->connector($source)->policy();

        self::assertSame(30, $policy->maxRequestsPerSync);
        self::assertSame(300, $policy->dailyQuota);
        self::assertSame(1_000, $policy->minimumDelayMilliseconds);
        self::assertTrue($policy->respectsRobotsTxt);
    }

    public function testLegacySingleListingPolicyKeepsConfiguredPageBudget(): void
    {
        $source = $this->source([
            'maxPages' => 5,
            'maxDetails' => 20,
        ]);

        self::assertSame(25, $this->connector($source)->policy()->maxRequestsPerSync);
    }

    /** @param array<string, mixed> $configuration */
    private function source(array $configuration): CustomScraperSource
    {
        $source = (new CustomScraperSource('Example Jobs', 'https://jobs.example.com/jobs'))->fill([
            ...$configuration,
            'enabled' => true,
            'mode' => 'HTTP',
            'authorizationConfirmed' => true,
            'authorizationCheckedAt' => '2026-08-12',
            'authorizationReference' => 'Autorisation écrite de test.',
        ]);

        $id = new \ReflectionProperty(CustomScraperSource::class, 'id');
        $id->setValue($source, 42);

        return $source;
    }

    private function connector(CustomScraperSource $source): CustomScraperJobConnector
    {
        $extraction = (new \ReflectionClass(CustomScraperExtractionService::class))->newInstanceWithoutConstructor();
        self::assertInstanceOf(CustomScraperExtractionService::class, $extraction);

        return new CustomScraperJobConnector($source, $extraction);
    }
}
