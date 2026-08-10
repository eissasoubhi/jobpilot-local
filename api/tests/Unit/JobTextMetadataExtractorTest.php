<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Application\JobTextMetadataExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JobTextMetadataExtractorTest extends TestCase
{
    #[DataProvider('metadataCases')]
    public function testExtractsDeterministicJobMetadata(string $text, array $expected): void
    {
        self::assertSame($expected, (new JobTextMetadataExtractor())->extract($text));
    }

    /** @return iterable<string, array{string, array{contractType:string, workMode:string, tjmMin:int|null, tjmMax:int|null}}> */
    public static function metadataCases(): iterable
    {
        yield 'CDI hybride' => [
            'CDI à Paris, hybride avec 2 jours de télétravail par semaine.',
            ['contractType' => 'CDI', 'workMode' => 'Hybride', 'tjmMin' => null, 'tjmMax' => null],
        ];

        yield 'freelance remote TJM range' => [
            'Mission freelance full remote. TJM : 500-600 €.',
            ['contractType' => 'Freelance', 'workMode' => 'Télétravail', 'tjmMin' => 500, 'tjmMax' => 600],
        ];

        yield 'CDD onsite' => [
            'CDD de 12 mois en présentiel sur site.',
            ['contractType' => 'CDD', 'workMode' => 'Sur site', 'tjmMin' => null, 'tjmMax' => null],
        ];

        yield 'daily rate alternative syntax' => [
            'Indépendant, hybride. Budget 450 à 520 €/jour.',
            ['contractType' => 'Freelance', 'workMode' => 'Hybride', 'tjmMin' => 450, 'tjmMax' => 520],
        ];

        yield 'no metadata' => [
            'Développeur PHP Symfony pour renforcer une équipe produit.',
            ['contractType' => '', 'workMode' => '', 'tjmMin' => null, 'tjmMax' => null],
        ];
    }
}
