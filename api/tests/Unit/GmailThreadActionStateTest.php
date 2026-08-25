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

final class GmailThreadActionStateTest extends TestCase
{
    private string $privateDir;

    protected function setUp(): void
    {
        $this->privateDir = sys_get_temp_dir().'/jobpilot-gmail-thread-'.bin2hex(random_bytes(8));
        mkdir($this->privateDir, 0700, true);
        $_SERVER['GOOGLE_CLIENT_ID'] = 'client-id';
        $_SERVER['GOOGLE_CLIENT_SECRET'] = 'client-secret';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->privateDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->privateDir);
        unset($_SERVER['GOOGLE_CLIENT_ID'], $_SERVER['GOOGLE_CLIENT_SECRET']);
    }

    public function testUserReplyClosesOlderThreadActionAndLaterRecruiterReplyReopensIt(): void
    {
        $store = new GmailTokenStore($this->privateDir, 'test-encryption-key');
        $store->saveToken([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 3600,
            'created_at' => time(),
            'scope' => 'https://www.googleapis.com/auth/gmail.readonly',
        ]);

        $http = new MockHttpClient([
            new MockResponse(json_encode([
                'messages' => [
                    ['id' => 'sent-user', 'threadId' => 'thread-opportunity'],
                    ['id' => 'old-recruiter', 'threadId' => 'thread-opportunity'],
                    ['id' => 'new-recruiter', 'threadId' => 'thread-opportunity'],
                ],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode($this->message(
                'sent-user',
                1787227200000,
                'Aissa <aissa@example.com>',
                'recruiter@example.com',
                'Re: Nouvelle mission Symfony',
                'Merci, je suis disponible pour échanger.',
                ['SENT'],
            ), JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode($this->message(
                'old-recruiter',
                1787140800000,
                'Recruiter <recruiter@example.com>',
                'aissa@example.com',
                'Nouvelle mission Symfony',
                'Bonjour, nous recherchons un consultant Symfony pour une nouvelle mission.',
            ), JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode($this->message(
                'new-recruiter',
                1787313600000,
                'Recruiter <recruiter@example.com>',
                'aissa@example.com',
                'Re: Nouvelle mission Symfony',
                'Pouvez-vous nous transmettre vos disponibilités pour la suite ?',
            ), JSON_THROW_ON_ERROR)),
        ]);

        $inboxRepository = $this->createMock(EntityRepository::class);
        $inboxRepository->method('findOneBy')->willReturn(null);
        $inboxRepository->method('findBy')->willReturn([]);
        $applicationRepository = $this->createMock(EntityRepository::class);
        $applicationRepository->method('findBy')->willReturn([]);

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            static fn (string $class): EntityRepository => $class === InboxMessage::class
                ? $inboxRepository
                : $applicationRepository,
        );
        $em->expects(self::exactly(3))
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                if ($entity instanceof InboxMessage) {
                    $persisted[$entity->getGmailMessageId()] = $entity;
                }
            });

        $service = new GmailService(
            $http,
            $store,
            $em,
            new GmailMessageDecoder(),
            new GmailMessageClassifier(),
            new GmailJobAlertExtractor(),
        );

        $service->collectJobOffers();

        self::assertCount(3, $persisted);
        self::assertFalse($persisted['sent-user']->isActionRequired());
        self::assertTrue($persisted['sent-user']->isProcessed());

        self::assertTrue($persisted['old-recruiter']->isActionRequired());
        self::assertTrue($persisted['old-recruiter']->isProcessed());

        self::assertTrue($persisted['new-recruiter']->isActionRequired());
        self::assertFalse($persisted['new-recruiter']->isProcessed());
        self::assertSame(1, $service->lastSyncSummary()['actionRequired']);
    }

    /**
     * @param list<string> $labelIds
     * @return array<string, mixed>
     */
    private function message(
        string $id,
        int $internalDate,
        string $from,
        string $to,
        string $subject,
        string $body,
        array $labelIds = ['INBOX'],
    ): array {
        return [
            'id' => $id,
            'threadId' => 'thread-opportunity',
            'labelIds' => $labelIds,
            'internalDate' => (string) $internalDate,
            'snippet' => $body,
            'payload' => [
                'mimeType' => 'text/plain',
                'headers' => [
                    ['name' => 'From', 'value' => $from],
                    ['name' => 'To', 'value' => $to],
                    ['name' => 'Subject', 'value' => $subject],
                ],
                'body' => ['data' => $this->encodeBase64Url($body)],
            ],
        ];
    }

    private function encodeBase64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
