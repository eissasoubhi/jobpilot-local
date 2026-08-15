<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_gmail_message', columns: ['gmail_message_id'])]
#[ORM\Index(columns: ['received_at'], name: 'idx_inbox_received')]
#[ORM\Index(columns: ['category'], name: 'idx_inbox_category')]
#[ORM\Index(columns: ['action_required', 'processed'], name: 'idx_inbox_action')]
final class InboxMessage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $gmailMessageId;

    #[ORM\Column(length: 255)]
    private string $threadId;

    #[ORM\Column(length: 255)]
    private string $sender = '';

    #[ORM\Column(length: 255)]
    private string $recipient = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $replyTo = null;

    #[ORM\Column(length: 500)]
    private string $subject = '';

    #[ORM\Column(type: 'text')]
    private string $snippet = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bodyText = null;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(length: 50)]
    private string $category = 'UNKNOWN';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $classificationReason = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $sourcePlatform = null;

    #[ORM\Column]
    private bool $actionRequired = false;

    #[ORM\Column]
    private bool $processed = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Application $application = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?JobOffer $jobOffer = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $matchedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $gmailMessageId, string $threadId)
    {
        $gmailMessageId = trim($gmailMessageId);
        if ($gmailMessageId === '') {
            throw new \InvalidArgumentException('Identifiant du message Gmail manquant.');
        }

        $this->gmailMessageId = $gmailMessageId;
        $this->threadId = trim($threadId);
        $this->receivedAt = $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGmailMessageId(): string
    {
        return $this->gmailMessageId;
    }

    public function getThreadId(): string
    {
        return $this->threadId;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function isActionRequired(): bool
    {
        return $this->actionRequired;
    }

    public function isProcessed(): bool
    {
        return $this->processed;
    }

    public function fill(
        string $sender,
        string $subject,
        string $snippet,
        \DateTimeImmutable $receivedAt,
        string $category,
        string $recipient = '',
        ?string $replyTo = null,
        string $bodyText = '',
        bool $actionRequired = false,
        ?string $classificationReason = null,
        ?string $sourcePlatform = null,
    ): self {
        $this->sender = mb_substr(trim($sender), 0, 255);
        $this->recipient = mb_substr(trim($recipient), 0, 255);
        $this->replyTo = $replyTo !== null && trim($replyTo) !== ''
            ? mb_substr(trim($replyTo), 0, 255)
            : null;
        $this->subject = mb_substr(trim($subject), 0, 500);
        $this->snippet = mb_substr(trim($snippet), 0, 4_000);
        $bodyText = trim($bodyText);
        $this->bodyText = $bodyText !== '' ? mb_substr($bodyText, 0, 100_000) : null;
        $this->receivedAt = $receivedAt;
        $this->category = mb_substr(trim($category) !== '' ? trim($category) : 'UNKNOWN', 0, 50);
        $this->classificationReason = $classificationReason !== null && trim($classificationReason) !== ''
            ? mb_substr(trim($classificationReason), 0, 500)
            : null;
        $this->sourcePlatform = $sourcePlatform !== null && trim($sourcePlatform) !== ''
            ? mb_substr(trim($sourcePlatform), 0, 100)
            : null;
        $this->actionRequired = $actionRequired;

        return $this;
    }

    public function associate(?Application $application, ?JobOffer $jobOffer = null): void
    {
        $this->application = $application;
        $this->jobOffer = $jobOffer ?? $application?->getJobOffer();
        $this->matchedAt = $this->application !== null || $this->jobOffer !== null
            ? new \DateTimeImmutable()
            : null;
    }

    public function markProcessed(bool $processed = true): void
    {
        $this->processed = $processed;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gmailMessageId' => $this->gmailMessageId,
            'threadId' => $this->threadId,
            'gmailUrl' => $this->threadId !== '' ? 'https://mail.google.com/mail/u/0/#inbox/'.$this->threadId : null,
            'sender' => $this->sender,
            'recipient' => $this->recipient,
            'replyTo' => $this->replyTo,
            'subject' => $this->subject,
            'snippet' => $this->snippet,
            'bodyText' => $this->bodyText,
            'receivedAt' => $this->receivedAt->format(DATE_ATOM),
            'category' => $this->category,
            'classificationReason' => $this->classificationReason,
            'sourcePlatform' => $this->sourcePlatform,
            'actionRequired' => $this->actionRequired,
            'processed' => $this->processed,
            'application' => $this->application === null ? null : [
                'id' => $this->application->getId(),
                'status' => $this->application->getStatus(),
            ],
            'jobOffer' => $this->jobOffer === null ? null : [
                'id' => $this->jobOffer->getId(),
                'title' => $this->jobOffer->getTitle(),
                'company' => $this->jobOffer->getCompany(),
            ],
            'matchedAt' => $this->matchedAt?->format(DATE_ATOM),
        ];
    }
}
