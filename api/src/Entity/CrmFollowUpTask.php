<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'crm_follow_up_task')]
#[ORM\Index(name: 'idx_crm_follow_up_due_status', columns: ['due_at', 'completed_at'])]
final class CrmFollowUpTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'organization_key', length: 191)]
    private string $organizationKey;

    #[ORM\Column(name: 'contact_key', length: 255, nullable: true)]
    private ?string $contactKey;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note;

    #[ORM\Column(name: 'due_at', type: 'date_immutable')]
    private \DateTimeImmutable $dueAt;

    #[ORM\Column(name: 'completed_at', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $organizationKey,
        ?string $contactKey,
        mixed $title,
        mixed $note,
        \DateTimeImmutable $dueAt,
    ) {
        $this->organizationKey = $this->validateKey($organizationKey, 191, 'organization');
        $this->contactKey = $contactKey === null ? null : $this->validateKey($contactKey, 255, 'contact');
        $this->title = $this->validateTitle($title);
        $this->note = $this->validateNote($note);
        $this->dueAt = $dueAt->setTime(0, 0);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOrganizationKey(): string { return $this->organizationKey; }
    public function getContactKey(): ?string { return $this->contactKey; }
    public function getTitle(): string { return $this->title; }
    public function getNote(): ?string { return $this->note; }
    public function getDueAt(): \DateTimeImmutable { return $this->dueAt; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function isCompleted(): bool { return $this->completedAt !== null; }

    public function setCompleted(bool $completed): self
    {
        $this->completedAt = $completed ? ($this->completedAt ?? new \DateTimeImmutable()) : null;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organizationKey' => $this->organizationKey,
            'contactKey' => $this->contactKey,
            'title' => $this->title,
            'note' => $this->note,
            'dueAt' => $this->dueAt->format('Y-m-d'),
            'completed' => $this->isCompleted(),
            'completedAt' => $this->completedAt?->format(DATE_ATOM),
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    private function validateKey(string $value, int $maximumLength, string $label): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maximumLength || str_contains($value, "\0")) {
            throw new \InvalidArgumentException(sprintf('CRM %s key is invalid.', $label));
        }

        return $value;
    }

    private function validateTitle(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value) > 180 || preg_match('/[\r\n]/u', $value) === 1 || str_contains($value, "\0")) {
            throw new \InvalidArgumentException('Follow-up title must be a single line of at most 180 characters.');
        }

        return $value;
    }

    private function validateNote(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if (str_contains($value, "\0") || mb_strlen($value) > 2000) {
            throw new \InvalidArgumentException('Follow-up note must contain at most 2000 characters.');
        }

        return $value === '' ? null : $value;
    }
}
