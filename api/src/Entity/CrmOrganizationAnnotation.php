<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'crm_organization_annotation')]
final class CrmOrganizationAnnotation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'organization_key', length: 191, unique: true)]
    private string $organizationKey;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $organizationKey)
    {
        $organizationKey = trim($organizationKey);
        if ($organizationKey === '' || mb_strlen($organizationKey) > 191) {
            throw new \InvalidArgumentException('CRM organization key is invalid.');
        }

        $this->organizationKey = $organizationKey;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganizationKey(): string
    {
        return $this->organizationKey;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function update(mixed $displayName, mixed $note): self
    {
        $displayName = $this->normalizeOptionalText($displayName);
        $note = $this->normalizeOptionalText($note);

        if ($displayName !== null) {
            if (mb_strlen($displayName) > 255 || preg_match('/[\r\n]/u', $displayName) === 1) {
                throw new \InvalidArgumentException('CRM organization display name must be a single line of at most 255 characters.');
            }
        }
        if ($note !== null && mb_strlen($note) > 5000) {
            throw new \InvalidArgumentException('CRM organization note must not exceed 5000 characters.');
        }

        $this->displayName = $displayName;
        $this->note = $note;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->displayName === null && $this->note === null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organizationKey' => $this->organizationKey,
            'displayName' => $this->displayName,
            'note' => $this->note,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    private function normalizeOptionalText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if (str_contains($value, "\0")) {
            throw new \InvalidArgumentException('CRM organization annotation contains an invalid character.');
        }

        return $value === '' ? null : $value;
    }
}
