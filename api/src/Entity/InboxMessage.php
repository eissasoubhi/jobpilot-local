<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_gmail_message', columns: ['gmail_message_id'])]
class InboxMessage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 255)] private string $gmailMessageId;
    #[ORM\Column(length: 255)] private string $threadId;
    #[ORM\Column(length: 255)] private string $sender = '';
    #[ORM\Column(length: 500)] private string $subject = '';
    #[ORM\Column(type: 'text')] private string $snippet = '';
    #[ORM\Column] private \DateTimeImmutable $receivedAt;
    #[ORM\Column(length: 50)] private string $category = 'UNKNOWN';
    #[ORM\Column] private bool $processed = false;
    #[ORM\Column] private \DateTimeImmutable $createdAt;

    public function __construct(string $gmailMessageId, string $threadId)
    {
        $this->gmailMessageId = $gmailMessageId;
        $this->threadId = $threadId;
        $this->receivedAt = $this->createdAt = new \DateTimeImmutable();
    }

    public function fill(string $sender, string $subject, string $snippet, \DateTimeImmutable $receivedAt, string $category): self
    {
        $this->sender = $sender;
        $this->subject = $subject;
        $this->snippet = $snippet;
        $this->receivedAt = $receivedAt;
        $this->category = $category;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gmailMessageId' => $this->gmailMessageId,
            'threadId' => $this->threadId,
            'sender' => $this->sender,
            'subject' => $this->subject,
            'snippet' => $this->snippet,
            'receivedAt' => $this->receivedAt->format(DATE_ATOM),
            'category' => $this->category,
            'processed' => $this->processed,
        ];
    }
}
