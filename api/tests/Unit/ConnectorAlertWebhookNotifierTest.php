<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Infrastructure\Monitoring\ConnectorAlertWebhookNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConnectorAlertWebhookNotifierTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/jobpilot-connector-alert-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    public function testSendsSignedPayloadOnceForAnUnchangedAlertState(): void
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = compact('method', 'url', 'options');

            return new MockResponse('', ['http_code' => 204]);
        });
        $notifier = $this->notifier($http, signingSecret: 'alert-secret');

        self::assertSame(ConnectorAlertWebhookNotifier::RESULT_SENT, $notifier->notify([$this->alert()], 21600));
        self::assertSame(ConnectorAlertWebhookNotifier::RESULT_UNCHANGED, $notifier->notify([$this->alert(overdueBySeconds: 99999)], 21600));
        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('https://hooks.example.test/jobpilot', $requests[0]['url']);

        $payload = json_decode((string) $requests[0]['options']['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('connector.freshness.alert', $payload['event']);
        self::assertSame(1, $payload['alertCount']);
        self::assertSame('symfony-jobs', $payload['connectors'][0]['code']);
        self::assertSame('STALE', $payload['connectors'][0]['status']);

        $headers = json_encode($requests[0]['options']['headers'] ?? [], JSON_THROW_ON_ERROR);
        self::assertStringContainsString('X-JobPilot-Signature', $headers);
        self::assertFileExists($this->directory.'/state.json');
    }

    public function testRecoveryClearsFingerprintSoARecurringFailureCanNotifyAgain(): void
    {
        $requestCount = 0;
        $http = new MockHttpClient(static function () use (&$requestCount): MockResponse {
            ++$requestCount;

            return new MockResponse('', ['http_code' => 200]);
        });
        $notifier = $this->notifier($http);

        self::assertSame(ConnectorAlertWebhookNotifier::RESULT_SENT, $notifier->notify([$this->alert()], 21600));
        self::assertSame(ConnectorAlertWebhookNotifier::RESULT_NO_ALERT, $notifier->notify([], 21600));
        self::assertSame(ConnectorAlertWebhookNotifier::RESULT_SENT, $notifier->notify([$this->alert()], 21600));
        self::assertSame(2, $requestCount);
    }

    public function testUnconfiguredWebhookNeverCallsNetwork(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new \LogicException('Network must not be called.');
        });
        $notifier = new ConnectorAlertWebhookNotifier(
            $http,
            $this->directory.'/state.json',
            '',
            '',
            '',
        );

        self::assertSame(ConnectorAlertWebhookNotifier::RESULT_DISABLED, $notifier->notify([$this->alert()], 21600));
    }

    public function testRejectsAHostThatIsNotExplicitlyAllowlisted(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new \LogicException('Network must not be called.');
        });
        $notifier = new ConnectorAlertWebhookNotifier(
            $http,
            $this->directory.'/state.json',
            'https://attacker.example.test/hook',
            'hooks.example.test',
            '',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not explicitly allowlisted');
        $notifier->notify([$this->alert()], 21600);
    }

    public function testFailedDeliveryIsNotMarkedAsSent(): void
    {
        $requestCount = 0;
        $http = new MockHttpClient(static function () use (&$requestCount): MockResponse {
            ++$requestCount;

            return new MockResponse('failure', ['http_code' => 503]);
        });
        $notifier = $this->notifier($http);

        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $notifier->notify([$this->alert()], 21600);
                self::fail('The webhook failure must be reported.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('HTTP 503', $exception->getMessage());
            }
        }

        self::assertSame(2, $requestCount);
        self::assertFileDoesNotExist($this->directory.'/state.json');
    }

    private function notifier(MockHttpClient $http, string $signingSecret = ''): ConnectorAlertWebhookNotifier
    {
        return new ConnectorAlertWebhookNotifier(
            $http,
            $this->directory.'/state.json',
            'https://hooks.example.test/jobpilot',
            'hooks.example.test',
            $signingSecret,
        );
    }

    /** @return array<string, mixed> */
    private function alert(int $overdueBySeconds = 43200): array
    {
        return [
            'code' => 'symfony-jobs',
            'name' => 'Symfony Jobs',
            'status' => 'STALE',
            'alert' => true,
            'lastSyncedAt' => '2026-08-04T10:00:00+00:00',
            'nextExpectedAt' => '2026-08-04T16:00:00+00:00',
            'overdueBySeconds' => $overdueBySeconds,
            'reason' => 'The connector missed several synchronization intervals.',
        ];
    }
}
