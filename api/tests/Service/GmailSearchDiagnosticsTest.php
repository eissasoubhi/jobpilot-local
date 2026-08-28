<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\JobDiscovery\Domain\Connector\SearchDiagnosticsConnector;
use App\Service\GmailJobProvider;
use App\Service\GmailService;
use App\Service\GmailTokenStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class GmailSearchDiagnosticsTest extends TestCase
{
    /**
     * @param array<string, int> $summary
     */
    #[DataProvider('diagnosticOutcomeProvider')]
    public function testProviderExposesMailboxCountersSeparatelyFromExtractedOffers(
        array $summary,
        string $expectedOutcome,
    ): void {
        $gmail = (new ReflectionClass(GmailService::class))->newInstanceWithoutConstructor();
        $lastSyncSummary = new ReflectionProperty(GmailService::class, 'lastSyncSummary');
        $lastSyncSummary->setValue($gmail, $summary);

        $tokenStore = (new ReflectionClass(GmailTokenStore::class))->newInstanceWithoutConstructor();
        $provider = new GmailJobProvider($gmail, $tokenStore);

        self::assertInstanceOf(SearchDiagnosticsConnector::class, $provider);
        self::assertSame([
            'source' => 'gmail',
            'messagesMatched' => $summary['found'],
            'messagesImported' => $summary['imported'],
            'messagesAlreadyKnown' => $summary['duplicates'],
            'messagesFailed' => $summary['failed'],
            'offersExtracted' => $summary['offersFound'],
            'messagesAssociated' => $summary['associated'],
            'messagesActionRequired' => $summary['actionRequired'],
            'outcome' => $expectedOutcome,
        ], $provider->searchDiagnostics());
    }

    /** @return iterable<string, array{0: array<string, int>, 1: string}> */
    public static function diagnosticOutcomeProvider(): iterable
    {
        yield 'no mailbox messages match the configured Gmail search' => [[
            'found' => 0,
            'imported' => 0,
            'duplicates' => 0,
            'failed' => 0,
            'offersFound' => 0,
            'associated' => 0,
            'actionRequired' => 0,
        ], 'no_messages_matched'];

        yield 'all matching messages were already known' => [[
            'found' => 23,
            'imported' => 0,
            'duplicates' => 23,
            'failed' => 0,
            'offersFound' => 0,
            'associated' => 0,
            'actionRequired' => 0,
        ], 'no_new_messages'];

        yield 'new messages were read but contained no extractable job offer' => [[
            'found' => 23,
            'imported' => 4,
            'duplicates' => 19,
            'failed' => 0,
            'offersFound' => 0,
            'associated' => 2,
            'actionRequired' => 1,
        ], 'messages_without_offers'];

        yield 'job offers were extracted' => [[
            'found' => 23,
            'imported' => 4,
            'duplicates' => 19,
            'failed' => 0,
            'offersFound' => 3,
            'associated' => 2,
            'actionRequired' => 1,
        ], 'offers_extracted'];
    }
}
