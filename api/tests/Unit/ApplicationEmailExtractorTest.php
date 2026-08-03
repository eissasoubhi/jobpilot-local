<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\ApplicationEmailExtractor;
use PHPUnit\Framework\TestCase;

final class ApplicationEmailExtractorTest extends TestCase
{
    private ApplicationEmailExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new ApplicationEmailExtractor();
    }

    public function testExtractsRecruitmentAddressFromContext(): void
    {
        self::assertSame(
            'recrutement@example.com',
            $this->extractor->extract('Envoyez votre candidature à Recrutement@Example.com avec votre CV.'),
        );
    }

    public function testRejectsGenericAndNoReplyAddresses(): void
    {
        self::assertNull($this->extractor->extract(
            'Pour toute question : support@example.com. Notification : no-reply@example.com.',
        ));
    }

    public function testUsesRecruitmentLocalPartWithoutNearbyKeyword(): void
    {
        self::assertSame(
            'jobs@example.org',
            $this->extractor->extract('Contact principal : jobs@example.org'),
        );
    }

    public function testDoesNotSelectUnrelatedPersonalAddress(): void
    {
        self::assertNull($this->extractor->extract('Référence technique : developer@example.org'));
    }
}
