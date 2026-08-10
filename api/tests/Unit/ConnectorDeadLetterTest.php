<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\ConnectorDeadLetter;
use PHPUnit\Framework\TestCase;

final class ConnectorDeadLetterTest extends TestCase
{
    public function testFailureEscalationResolutionAndFreshFailureLifecycle(): void
    {
        $entry = new ConnectorDeadLetter(
            'custom-scraper-42',
            ConnectorDeadLetter::STAGE_IMPORT,
            str_repeat('a', 64),
            \RuntimeException::class,
            'Première erreur',
            'JOB-42',
            'https://jobs.example.test/jobs/42',
            'Développeur Symfony',
        );

        self::assertSame(ConnectorDeadLetter::STATE_TRACKING, $entry->getState());
        self::assertSame(1, $entry->getFailureCount());
        self::assertFalse($entry->isOpen());

        $entry->recordFailure(\RuntimeException::class, 'Deuxième erreur');
        self::assertSame(ConnectorDeadLetter::STATE_TRACKING, $entry->getState());
        self::assertSame(2, $entry->getFailureCount());

        $entry->recordFailure(\RuntimeException::class, 'Troisième erreur');
        self::assertSame(ConnectorDeadLetter::STATE_OPEN, $entry->getState());
        self::assertSame(3, $entry->getFailureCount());
        self::assertTrue($entry->isOpen());

        $entry->resolve();
        self::assertSame(ConnectorDeadLetter::STATE_RESOLVED, $entry->getState());
        self::assertNotNull($entry->toArray()['resolvedAt']);

        $entry->recordFailure(\LogicException::class, 'Nouvelle erreur après résolution');
        self::assertSame(ConnectorDeadLetter::STATE_TRACKING, $entry->getState());
        self::assertSame(1, $entry->getFailureCount());
        self::assertNull($entry->toArray()['resolvedAt']);
        self::assertSame(\LogicException::class, $entry->toArray()['errorClass']);
    }

    public function testEvidenceIsBoundedAndNeverRequiresRawPayload(): void
    {
        $entry = new ConnectorDeadLetter(
            'source',
            ConnectorDeadLetter::STAGE_SEARCH,
            str_repeat('b', 64),
            \RuntimeException::class,
            str_repeat('x', 2_000),
            null,
            null,
            str_repeat('T', 500),
        );

        $data = $entry->toArray();
        self::assertSame(1_000, mb_strlen((string) $data['errorMessage']));
        self::assertSame(255, mb_strlen((string) $data['title']));
        self::assertArrayNotHasKey('rawData', $data);
        self::assertArrayNotHasKey('payload', $data);
    }
}
