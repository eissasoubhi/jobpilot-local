<?php
namespace App\Tests\Service;

use App\Service\LanguageDetector;
use PHPUnit\Framework\TestCase;

final class LanguageDetectorTest extends TestCase
{
    public function testFrench(): void { self::assertSame('fr', (new LanguageDetector())->detect('Nous recherchons un développeur Symfony expérimenté pour rejoindre notre équipe.')); }
    public function testEnglish(): void { self::assertSame('en', (new LanguageDetector())->detect('We are looking for a senior Symfony developer to join our engineering team.')); }
}
