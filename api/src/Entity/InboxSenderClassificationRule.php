<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_inbox_sender_rule_key', columns: ['sender_key'])]
final class InboxSenderClassificationRule
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $senderKey;

    #[ORM\Column(length: 50)]
    private string $category;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $sender, string $category = 'JOB_ALERT')
    {
        $this->senderKey = self::senderKey($sender);
        if ($this->senderKey === '') {
            throw new \InvalidArgumentException('Expéditeur invalide pour une règle Inbox.');
        }

        $this->setCategory($category);
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSenderKey(): string
    {
        return $this->senderKey;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): void
    {
        $category = strtoupper(trim($category));
        if (!in_array($category, ['JOB_ALERT', 'MARKETING'], true)) {
            throw new \InvalidArgumentException('Catégorie de règle Inbox non autorisée.');
        }

        $this->category = $category;
        if (isset($this->updatedAt)) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public static function senderKey(string $sender): string
    {
        $sender = mb_strtolower(trim($sender));
        if (preg_match('/<([^<>]+)>/', $sender, $matches) === 1) {
            $sender = trim($matches[1]);
        }

        if (filter_var($sender, FILTER_VALIDATE_EMAIL) === false) {
            return '';
        }

        return mb_substr($sender, 0, 255);
    }
}
