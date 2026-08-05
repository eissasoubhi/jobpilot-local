<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CrmContactCorrection;
use PHPUnit\Framework\TestCase;

final class CrmContactCorrectionTest extends TestCase
{
    public function testStoresNormalizedCorrectionsWithoutChangingStableKeys(): void
    {
        $correction = (new CrmContactCorrection('acme consulting', 'jane@acme.test'))->update(
            '  Jane Recruiter France  ',
            ' JANE.FRANCE@ACME.TEST ',
            ' +33 6 10 20 30 40 ',
        );

        self::assertSame('acme consulting', $correction->getOrganizationKey());
        self::assertSame('jane@acme.test', $correction->getContactKey());
        self::assertSame('Jane Recruiter France', $correction->getCorrectedName());
        self::assertSame('jane.france@acme.test', $correction->getCorrectedEmail());
        self::assertSame('+33 6 10 20 30 40', $correction->getCorrectedPhone());
        self::assertFalse($correction->isEmpty());
    }

    public function testEmptyValuesClearAllCorrections(): void
    {
        $correction = (new CrmContactCorrection('acme consulting', 'jane@acme.test'))->update(
            'Jane France',
            'jane.france@acme.test',
            '+33 6 10 20 30 40',
        );

        $correction->update(' ', null, '');

        self::assertNull($correction->getCorrectedName());
        self::assertNull($correction->getCorrectedEmail());
        self::assertNull($correction->getCorrectedPhone());
        self::assertTrue($correction->isEmpty());
    }

    public function testRejectsAnInvalidEmailCorrection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('email address is invalid');

        (new CrmContactCorrection('acme', 'contact'))->update(null, 'not-an-email', null);
    }

    public function testRejectsAMultilineNameCorrection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('single line');

        (new CrmContactCorrection('acme', 'contact'))->update("Jane\nRecruiter", null, null);
    }

    public function testRejectsAPhoneWithoutAnyDigit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('containing a digit');

        (new CrmContactCorrection('acme', 'contact'))->update(null, null, 'unknown');
    }

    public function testRejectsAnInvalidStableKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CrmContactCorrection('', 'contact');
    }
}
