<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Application\CustomScraperDetailPriority;
use PHPUnit\Framework\TestCase;

final class CustomScraperDetailPriorityTest extends TestCase
{
    public function testRanksProfileTechnologyBeforeOriginalListingOrder(): void
    {
        $candidates = [
            ['title' => 'React Developer', 'description' => 'Frontend React TypeScript'],
            ['title' => 'Java Backend Developer', 'description' => 'Spring Boot Java'],
            ['title' => 'Senior Symfony Developer', 'description' => 'PHP Symfony'],
        ];

        $order = (new CustomScraperDetailPriority())->rank(
            $candidates,
            ['Symfony Developer', 'Backend PHP'],
            ['PHP', 'Symfony', 'API Platform'],
        );

        self::assertSame([2, 0, 1], $order);
    }

    public function testKeepsOriginalOrderWhenNoProfileTermsAreAvailable(): void
    {
        $candidates = [
            ['title' => 'React Developer'],
            ['title' => 'Symfony Developer'],
            ['title' => 'PHP Developer'],
        ];

        self::assertSame([0, 1, 2], (new CustomScraperDetailPriority())->rank($candidates, [], []));
    }

    public function testIgnoresGenericJobTitleWordsAsPrioritySignals(): void
    {
        $candidates = [
            ['title' => 'Senior React Developer'],
            ['title' => 'Junior Symfony Developer'],
        ];

        $order = (new CustomScraperDetailPriority())->rank(
            $candidates,
            ['Senior Symfony Developer'],
            ['Symfony'],
        );

        self::assertSame([1, 0], $order);
    }
}
