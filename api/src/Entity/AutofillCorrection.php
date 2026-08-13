<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'autofill_correction')]
#[ORM\UniqueConstraint(name: 'uniq_autofill_correction_scope', columns: ['host', 'field_fingerprint', 'canonical_key'])]
#[ORM\Index(name: 'idx_autofill_correction_host', columns: ['host'])]
final class AutofillCorrection
{
    public const ALLOWED_CONTROL_KINDS = ['select', 'multi-select', 'autocomplete'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $host;

    #[ORM\Column(length: 255)]
    private string $fieldFingerprint;

    #[ORM\Column(length: 180)]
    private string $canonicalKey;

    #[ORM\Column(length: 40)]
    private string $controlKind;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $originalValue = null;

    #[ORM\Column(type: 'text')]
    private string $correctedValue;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private int $usageCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct(
        string $host,
        string $fieldFingerprint,
        string $canonicalKey,
        string $controlKind,
        string $correctedValue,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->apply($host, $fieldFingerprint, $canonicalKey, $controlKind, $correctedValue, null);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function update(string $correctedValue, ?string $originalValue = null): self
    {
        $this->correctedValue = self::cleanValue($correctedValue, false);
        $this->originalValue = $originalValue === null ? $this->originalValue : self::cleanValue($originalValue, true);
        $this->enabled = true;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function markUsed(): self
    {
        ++$this->usageCount;
        $this->lastUsedAt = new \DateTimeImmutable();
        $this->updatedAt = $this->lastUsedAt;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'host' => $this->host,
            'fieldFingerprint' => $this->fieldFingerprint,
            'canonicalKey' => $this->canonicalKey,
            'controlKind' => $this->controlKind,
            'originalValue' => $this->originalValue,
            'correctedValue' => $this->correctedValue,
            'enabled' => $this->enabled,
            'usageCount' => $this->usageCount,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'lastUsedAt' => $this->lastUsedAt?->format(DATE_ATOM),
        ];
    }

    private function apply(
        string $host,
        string $fieldFingerprint,
        string $canonicalKey,
        string $controlKind,
        string $correctedValue,
        ?string $originalValue,
    ): void {
        $host = strtolower(trim($host));
        if ($host === '' || mb_strlen($host) > 255 || preg_match('/^[a-z0-9.-]+$/', $host) !== 1) {
            throw new \InvalidArgumentException('Le domaine de correction Autofill est invalide.');
        }

        $fieldFingerprint = trim($fieldFingerprint);
        if ($fieldFingerprint === '' || mb_strlen($fieldFingerprint) > 255) {
            throw new \InvalidArgumentException('L’empreinte du champ Autofill est invalide.');
        }

        $canonicalKey = trim($canonicalKey);
        if (preg_match('/^(identity|address|professional|preferences)\.[A-Za-z0-9._-]+$/', $canonicalKey) !== 1) {
            throw new \InvalidArgumentException('La clé canonique ne peut pas être apprise automatiquement.');
        }
        if (in_array($canonicalKey, ['preferences.desiredSalary', 'preferences.desiredTjm'], true)) {
            throw new \InvalidArgumentException('Les corrections de rémunération ne sont pas apprises automatiquement.');
        }

        $controlKind = strtolower(trim($controlKind));
        if (!in_array($controlKind, self::ALLOWED_CONTROL_KINDS, true)) {
            throw new \InvalidArgumentException('Ce type de contrôle ne peut pas être appris automatiquement.');
        }

        $this->host = $host;
        $this->fieldFingerprint = $fieldFingerprint;
        $this->canonicalKey = $canonicalKey;
        $this->controlKind = $controlKind;
        $this->correctedValue = self::cleanValue($correctedValue, false);
        $this->originalValue = $originalValue === null ? null : self::cleanValue($originalValue, true);
    }

    private static function cleanValue(string $value, bool $nullable): ?string
    {
        $value = trim(str_replace("\0", '', $value));
        if ($value === '') {
            if ($nullable) {
                return null;
            }
            throw new \InvalidArgumentException('La valeur corrigée ne peut pas être vide.');
        }
        if (mb_strlen($value) > 500) {
            throw new \InvalidArgumentException('La valeur corrigée est limitée à 500 caractères.');
        }

        return $value;
    }
}
