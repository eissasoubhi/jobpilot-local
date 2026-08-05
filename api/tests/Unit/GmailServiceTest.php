<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Application;
use App\Entity\InboxMessage;
use App\Messaging\Application\GmailJobAlertExtractor;
use App\Messaging\Application\GmailMessageClassifier;
use App\Messaging\Infrastructure\Gmail\GmailMessageDecoder;
use App\Service\GmailService;
use App\Service\GmailTokenStore;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GmailServiceTest extends TestCase
{
    private string $privateDir;

    protected function setUp(): void
    {
        $this->privateDir = sys_get_temp_dir().'/jobpilot-gmail-'.bin2hex(random_bytes(8));
        mkdir($this->privateDir, 0700, true);
        $_SERVER['GOOGLE_CLIENT_ID'] = 'client-id';
        $_SERVER['GOOGLE_CLIENT_SECRET'] = 'client-secret';
        $_SERVER['GOOGLE_REDIRECT_URI'] = 'http://localhost:8080/api/integrations/gmail/callback';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->privateDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->privateDir);
        unset(
            $_SERVER['GOOGLE_CLIENT_ID'],
            $_SERVER['GOOGLE_CLIENT_SECRET'],
            $_SERVER['GOOGLE_REDIRECT_URI'],
        );
    }

    public function testCallbackResolvesGrantedScopesWhenGoogleOmitsScopeFromTokenResponse(): void
    {
        $store = new GmailTokenStore($this->privateDir, 'test-encryption-key');
        $state = $store->createState();
        $http = new MockHttpClient([
            new MockResponse(json_encode([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'scope' => 'https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/gmail.send',
                'expires_in' => 3600,
            ], JSON_THROW_ON_ERROR)),
        ]);
        $service = $this->service($http, $store);

        $service->handleCallback('authorization-code', $state);

        self::assertTrue($service->hasReadPermission());
        self::assertTrue($service->hasSendPermission());
        $savedToken = $store->getToken();
        self::assertIsArray($savedToken);
        self::assertContains(
            'https://www.googleapis.com/auth/gmail.send',
            $savedToken['granted_scopes'],
        );
    }

    public function testItReadsACompleteAlertAndExtractsAJobOffer(): void
    {
        $store = new GmailTokenStore($this->privateDir, 'test-encryption-key');
        $store->saveToken([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
            'created_at' => time(),
            'scope' => 'https://www.googleapis.com/auth/gmail.readonly',
        ]);

        $html = '<html><body><p>Une nouvelle offre pour vous</p><a href="https://www.linkedin.com/jobs/view/123?trk=email_job_alert">Senior PHP Symfony Developer chez Example</a></body></html>';
        $http = new MockHttpClient([
            new MockResponse(json_encode([
                'messages' => [['id' => 'gmail-alert-1', 'threadId' => 'thread-1']],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'id' => 'gmail-alert-1',
                'threadId' => 'thread-1',
                'internalDate' => '1785888000000',
                'snippet' => 'Une nouvelle offre Symfony pour vous.',
                'payload' => [
                    'mimeType' => 'multipart/alternative',
                    'headers' => [
                        ['name' => 'From', 'value' => 'LinkedIn Jobs <jobs-noreply@linkedin.com>'],
                        ['name' => 'To', 'value' => 'aissa@example.com'],
                        ['name' => 'Subject', 'value' => 'Alerte emploi : Symfony'],
                    ],
                    'parts' => [[
                        'mimeType' => 'text/html',
                        'body' => ['data' => $this->encodeBase64Url($html)],
                    ]],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $inboxRepository = $this->createMock(EntityRepository::class);
        $inboxRepository->method('findOneBy')->willReturn(null);
        $applicationRepository = $this->createMock(EntityRepository::class);
        $applicationRepository->method('findBy')->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => $class === InboxMessage::class
                ? $inboxRepository
                : $applicationRepository,
        );
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(InboxMessage::class));

        $service = $this->service($http, $store, $em);
        $offers = $service->collectJobOffers();

        self::assertCount(1, $offers);
        self::assertSame('Senior PHP Symfony Developer', $offers[0]['title']);
        self::assertSame('Example', $offers[0]['company']);
        self::assertSame('https://www.linkedin.com/jobs/view/123', $offers[0]['sourceUrl']);
        self::assertSame('LinkedIn', $offers[0]['rawData']['alertPlatform']);
        self::assertSame(1, $service->lastSyncSummary()['imported']);
        self::assertSame(1, $service->lastSyncSummary()['offersFound']);
    }

    public function testItSendsTheExactMimeMessageWithTheCvAttachment(): void
    {
        $store = new GmailTokenStore($this->privateDir, 'test-encryption-key');
        $store->saveToken([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
            'created_at' => time(),
            'scope' => 'https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/gmail.send',
        ]);
        $cvPath = $this->privateDir.'/cv-test.pdf';
        file_put_contents($cvPath, '%PDF-jobpilot-test');

        $capturedMime = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedMime): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', $url);
            self::assertContains('Authorization: Bearer access-token', $options['normalized_headers']['authorization']);

            $body = $options['body'] ?? '';
            if (is_resource($body)) {
                $body = stream_get_contents($body);
            }
            self::assertIsString($body);
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertIsString($payload['raw'] ?? null);
            $capturedMime = $this->decodeBase64Url($payload['raw']);

            return new MockResponse(json_encode([
                'id' => 'gmail-message-123',
                'threadId' => 'gmail-thread-456',
            ], JSON_THROW_ON_ERROR));
        });
        $service = $this->service($http, $store);

        $result = $service->sendEmail(
            'destination@example.com',
            'Candidature – Développeur Symfony',
            "Bonjour,\n\nVoici ma candidature.",
            [[
                'path' => $cvPath,
                'filename' => 'CV Aïssa Symfony.pdf',
                'mimeType' => 'application/pdf',
            ]],
        );

        self::assertSame('gmail-message-123', $result['id']);
        self::assertIsString($capturedMime);
        self::assertStringContainsString("To: destination@example.com\r\n", $capturedMime);
        self::assertStringContainsString(
            'Subject: =?UTF-8?B?'.base64_encode('Candidature – Développeur Symfony').'?=',
            $capturedMime,
        );
        self::assertStringContainsString(base64_encode("Bonjour,\n\nVoici ma candidature."), str_replace("\r\n", '', $capturedMime));
        self::assertStringContainsString("filename*=UTF-8''CV%20A%C3%AFssa%20Symfony.pdf", $capturedMime);
        self::assertStringContainsString(base64_encode('%PDF-jobpilot-test'), str_replace("\r\n", '', $capturedMime));
    }

    public function testItReturnsAnActionableMessageWhenGoogleRejectsTheSendScope(): void
    {
        $store = new GmailTokenStore($this->privateDir, 'test-encryption-key');
        $store->saveToken([
            'access_token' => 'access-token',
            'expires_in' => 3600,
            'created_at' => time(),
            'scope' => 'https://www.googleapis.com/auth/gmail.send',
        ]);
        $http = new MockHttpClient(new MockResponse(json_encode([
            'error' => [
                'code' => 403,
                'message' => 'Request had insufficient authentication scopes.',
                'errors' => [['reason' => 'insufficientPermissions']],
            ],
        ], JSON_THROW_ON_ERROR), ['http_code' => 403]));
        $service = $this->service($http, $store);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('gmail.send manque');

        $service->sendEmail('destination@example.com', 'Sujet', 'Corps');
    }

    private function service(
        MockHttpClient $http,
        GmailTokenStore $store,
        ?EntityManagerInterface $em = null,
    ): GmailService {
        return new GmailService(
            $http,
            $store,
            $em ?? $this->createMock(EntityManagerInterface::class),
            new GmailMessageDecoder(),
            new GmailMessageClassifier(),
            new GmailJobAlertExtractor(),
        );
    }

    private function encodeBase64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decodeBase64Url(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
        $decoded = base64_decode($value, true);
        self::assertIsString($decoded);

        return $decoded;
    }
}
