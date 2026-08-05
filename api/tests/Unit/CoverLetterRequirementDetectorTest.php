<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\CoverLetterRequirementDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CoverLetterRequirementDetectorTest extends TestCase
{
    #[DataProvider('requiredTextProvider')]
    public function testDetectsAnExplicitCoverLetterRequest(string $text): void
    {
        self::assertTrue((new CoverLetterRequirementDetector())->isRequired($text));
    }

    /** @return iterable<string, array{string}> */
    public static function requiredTextProvider(): iterable
    {
        yield 'French direct request' => ['Merci de joindre une lettre de motivation à votre candidature.'];
        yield 'French CV and letter' => ['Envoyez votre CV et une lettre présentant votre motivation.'];
        yield 'English direct request' => ['Please submit a resume and a cover letter.'];
        yield 'English motivation letter' => ['A motivation letter is required for this role.'];
    }

    #[DataProvider('notRequiredTextProvider')]
    public function testDoesNotGenerateALetterWithoutARequirement(string $text): void
    {
        self::assertFalse((new CoverLetterRequirementDetector())->isRequired($text));
    }

    /** @return iterable<string, array{string}> */
    public static function notRequiredTextProvider(): iterable
    {
        yield 'normal job description' => ['Développeur Symfony pour concevoir des API et maintenir la plateforme.'];
        yield 'French explicitly unnecessary' => ['Candidature sans lettre de motivation, le CV suffit.'];
        yield 'French optional' => ['La lettre de motivation est facultative.'];
        yield 'French not required' => ['La lettre de candidature n’est pas requise.'];
        yield 'English not required' => ['A cover letter is not required.'];
        yield 'English optional' => ['Cover letter optional; resume only is accepted.'];
        yield 'empty text' => [''];
    }
}
