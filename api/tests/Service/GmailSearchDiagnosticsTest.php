<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\JobDiscovery\Domain\Connector\SearchDiagnosticsConnector;
use App\Service\GmailJobProvider;
use App\Service\GmailService;
use App\Service\GmailTokenStore;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class GmailSearchDiagnosticsTest extends TestCase
{
    public function testProviderExposesMailboxCountersSeparatelyFromExtractedOffers(): void
    {
        $gmail = (new ReflectionClass(GmailService::class))->newInstanceWithoutConstructor();
        $summary = new ReflectionProperty(GmailService::class, 'lastSyncSummary');
        $summary->setValue($gmail, [
            'found' => 23,
            'imported' => 4,
            'duplicates' => 19,
            'failed' => 0,
            'offersFound' => 0,
            'associated' => 2,
            'actionRequired' => 1,
        ]);

        $tokenStore = (new ReflectionClass(GmailTokenStore::class))->newInstanceWithoutConstructor();
        $provider = new GmailJobProvider($gmail, $tokenStore);

        self::assertInstanceOf(SearchDiagnosticsConnector::class, $provider);
        self::assertSame([
            'source' => 'gmail',
            'messagesMatched' => 23,
            'messagesImported' => 4,
            'messagesAlreadyKnown' => 19,
            'messagesFailed' => 0,
            'offersExtracted' => 0,
            'messagesAssociated' => 2,
            'messagesActionRequired' => 1,
        ], $provider->searchDiagnostics());
    }
}
