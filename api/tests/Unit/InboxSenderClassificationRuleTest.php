<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\InboxSenderClassificationRule;
use PHPUnit\Framework\TestCase;

final class InboxSenderClassificationRuleTest extends TestCase
{
    public function testItNormalizesMailboxAddressWithoutKeepingDisplayName(): void
    {
        self::assertSame(
            'alerts@example.com',
            InboxSenderClassificationRule::senderKey('Example Jobs <Alerts@Example.com>'),
        );
    }

    public function testItRejectsUnusableSenderAndUnsupportedCategory(): void
    {
        self::assertSame('', InboxSenderClassificationRule::senderKey('Example Jobs'));

        $this->expectException(\InvalidArgumentException::class);
        new InboxSenderClassificationRule('alerts@example.com', 'RECRUITER_OPPORTUNITY');
    }
}
