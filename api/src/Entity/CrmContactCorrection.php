<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'crm_contact_correction')]
#[ORM\UniqueConstraint(
    name: 'uniq_crm_contact_correction_org_contact',
    columns: ['organization_key', 'contact_key'],
)]
final class CrmContactCorrection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'organization_key', length: 191)]
    private string $organizationKey;

    #[ORM\Column(name: 'contact_key', length: 255)]
    private string $contactKey;

    #[ORM\Column(name: 'corrected_name', length: 255, nullable: true)]
    private ?string $correctedName = null;

    #[ORM\Column(name: 'corrected_email', length: 254, nullable: true)]
    private ?string $correctedEmail = null;

    #[ORM\Column(name: 'corrected_phone', length: 64, nullable: true)]
    private ?string $correctedPhone = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $organizationKey, string $contactKey)
    {
        $this->organizationKey = $this->validateKey($organizationKey, 191, 'organization');
        $this->contactKey = $this->validateKey($contactKey, 255, 'contact');
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

    public function getContactKey(): string
    {
        return $this->contactKey;
    }

    public function getCorrectedName(): ?string
    {
        return $this->correctedName;
    }

    public function getCorrectedEmail(): ?string
    {
        return $this->correctedEmail;
    }

    public function getCorrectedPhone(): ?string
    {
        return $this->correctedPhone;
    }

    public function update(mixed $name, mixed $email, mixed $phone): self
    {
        $name = $this->normalizeOptionalText($name);
        $email = $this->normalizeOptionalText($email);
        $phone = $this->normalizeOptionalText($phone);

        if ($name !== null && (mb_strlen($name) > 255 || preg_match('/[\r\n]/u', $name) === 1)) {
            throw new \InvalidArgumentException('CRM contact name must be a single line of at most 255 characters.');
        }

        if ($email !== null) {
            $email = mb_strtolower($email);
            if (mb_strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException('CRM contact email address is invalid.');
            }
        }

        if ($phone !== null) {
            if (mb_strlen($phone) > 64 || preg_match('/[\r\n]/u', $phone) === 1 || preg_match('/\d/u', $phone) !== 1) {
                throw new \InvalidArgumentException('CRM contact phone must be a single line of at most 64 characters containing a digit.');
            }
        }

        $this->correctedName = $name;
        $this->correctedEmail = $email;
        $this->correctedPhone = $phone;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->correctedName === null
            && $this->correctedEmail === null
            && $this->correctedPhone === null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organizationKey' => $this->organizationKey,
            'contactKey' => $this->contactKey,
            'correctedName' => $this->correctedName,
            'correctedEmail' => $this->correctedEmail,
            'correctedPhone' => $this->correctedPhone,
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

    private function normalizeOptionalText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if (str_contains($value, "\0")) {
            throw new \InvalidArgumentException('CRM contact correction contains an invalid character.');
        }

        return $value === '' ? null : $value;
    }
}
