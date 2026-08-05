<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CrmOrganizationAnnotation;
use PHPUnit\Framework\TestCase;

final class CrmOrganizationAnnotationTest extends TestCase
{
    public function testStoresTrimmedManualDataWithoutChangingTheOrganizationKey(): void
    {
        $annotation = (new CrmOrganizationAnnotation('acme consulting'))->update(
            '  ACME Consulting France  ',
            "  Contact prioritaire pour les missions Symfony.\nRelancer après sept jours.  ",
        );

        self::assertSame('acme consulting', $annotation->getOrganizationKey());
        self::assertSame('ACME Consulting France', $annotation->getDisplayName());
        self::assertSame("Contact prioritaire pour les missions Symfony.\nRelancer après sept jours.", $annotation->getNote());
        self::assertFalse($annotation->isEmpty());
        self::assertSame('acme consulting', $annotation->toArray()['organizationKey']);
    }

    public function testEmptyValuesClearTheAnnotation(): void
    {
        $annotation = (new CrmOrganizationAnnotation('final client'))->update('Final Client France', 'Note');
        $annotation->update(' ', null);

        self::assertNull($annotation->getDisplayName());
        self::assertNull($annotation->getNote());
        self::assertTrue($annotation->isEmpty());
    }

    public function testRejectsAMultilineDisplayName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('single line');

        (new CrmOrganizationAnnotation('acme'))->update("ACME\nFrance", null);
    }

    public function testRejectsAnOversizedNote(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('5000');

        (new CrmOrganizationAnnotation('acme'))->update(null, str_repeat('a', 5001));
    }

    public function testRejectsAnInvalidOrganizationKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CrmOrganizationAnnotation('');
    }
}
