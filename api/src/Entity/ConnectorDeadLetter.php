<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'connector_dead_letter')]
#[ORM\UniqueConstraint(name: 'uniq_connector_dead_letter_fingerprint', columns: ['connector_code', 'stage', 'fingerprint'])]
#[ORM\Index(columns: ['state', 'last_failed_at'], name: 'idx_connector_dead_letter_state')]
#[ORM\Index(columns: ['connector_code', 'state'], name: 'idx_connector_dead_letter_connector')]
final class ConnectorDeadLetter
{
    public const STATE_TRACKING = 'TRACKING';
    public const STATE_OPEN = 'OPEN';
    public const STATE_RESOLVED = 'RESOLVED';

    public const STAGE_SEARCH = 'SEARCH';
    public const STAGE_IMPORT = 'IMPORT';

    private const OPEN_THRESHOLD = 3;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $connectorCode;

    #[ORM\Column(length: 24)]
    private string $stage;

    #[ORM\Column(length: 64)]
    private string $fingerprint;

    #[ORM\Column(length: 24)]
    private string $state = self::STATE_TRACKING;

    #[ORM\Column]
    private int $failureCount = 1;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private string $errorClass;

    #[ORM\Column(type: 'text')]
    private string $errorMessage;

    #[ORM\Column]
    private \DateTimeImmutable $firstFailedAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastFailedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct(
        string $connectorCode,
        string $stage,
        string $fingerprint,
        string $errorClass,
        string $errorMessage,
        ?string $externalId = null,
        ?string $sourceUrl = null,
        ?string $title = null,
    ) {
        $this->connectorCode = mb_substr(strtolower(trim($connectorCode)), 0, 64);
        $this->stage = mb_substr(strtoupper(trim($stage)), 0, 24);
        $this->fingerprint = mb_substr(strtolower(trim($fingerprint)), 0, 64);
        if ($this->connectorCode === '' || $this->stage === '' || $this->fingerprint === '') {
            throw new \InvalidArgumentException('Connecteur, étape et empreinte sont obligatoires pour une dead-letter.');
        }

        $this->firstFailedAt = $this->lastFailedAt = new \DateTimeImmutable();
        $this->setEvidence($errorClass, $errorMessage, $externalId, $sourceUrl, $title);
    }

    public function getId(): ?int { return $this->id; }
    public function getConnectorCode(): string { return $this->connectorCode; }
    public function getStage(): string { return $this->stage; }
    public function getFingerprint(): string { return $this->fingerprint; }
    public function getState(): string { return $this->state; }
    public function getFailureCount(): int { return $this->failureCount; }
    public function isOpen(): bool { return $this->state === self::STATE_OPEN; }

    public function recordFailure(
        string $errorClass,
        string $errorMessage,
        ?string $externalId = null,
        ?string $sourceUrl = null,
        ?string $title = null,
    ): void {
        $now = new \DateTimeImmutable();
        if ($this->state === self::STATE_RESOLVED) {
            $this->state = self::STATE_TRACKING;
            $this->failureCount = 1;
            $this->firstFailedAt = $now;
            $this->resolvedAt = null;
        } else {
            ++$this->failureCount;
        }

        $this->lastFailedAt = $now;
        $this->setEvidence($errorClass, $errorMessage, $externalId, $sourceUrl, $title);
        if ($this->failureCount >= self::OPEN_THRESHOLD) {
            $this->state = self::STATE_OPEN;
        }
    }

    public function resolve(): void
    {
        if ($this->state === self::STATE_RESOLVED) {
            return;
        }

        $this->state = self::STATE_RESOLVED;
        $this->resolvedAt = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'connectorCode' => $this->connectorCode,
            'stage' => $this->stage,
            'fingerprint' => $this->fingerprint,
            'state' => $this->state,
            'failureCount' => $this->failureCount,
            'externalId' => $this->externalId,
            'sourceUrl' => $this->sourceUrl,
            'title' => $this->title,
            'errorClass' => $this->errorClass,
            'errorMessage' => $this->errorMessage,
            'firstFailedAt' => $this->firstFailedAt->format(DATE_ATOM),
            'lastFailedAt' => $this->lastFailedAt->format(DATE_ATOM),
            'resolvedAt' => $this->resolvedAt?->format(DATE_ATOM),
        ];
    }

    private function setEvidence(
        string $errorClass,
        string $errorMessage,
        ?string $externalId,
        ?string $sourceUrl,
        ?string $title,
    ): void {
        $this->errorClass = mb_substr(trim($errorClass) !== '' ? trim($errorClass) : 'RuntimeException', 0, 255);
        $message = trim(preg_replace('/\s+/u', ' ', $errorMessage) ?? $errorMessage);
        $this->errorMessage = mb_substr($message !== '' ? $message : 'Erreur sans message.', 0, 1_000);
        $this->externalId = $this->optional($externalId, 180);
        $this->sourceUrl = $this->optional($sourceUrl, 500);
        $this->title = $this->optional($title, 255);
    }

    private function optional(?string $value, int $maxLength): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }
}
