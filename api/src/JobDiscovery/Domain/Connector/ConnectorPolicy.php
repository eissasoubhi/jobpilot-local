<?php

declare(strict_types=1);

namespace App\JobDiscovery\Domain\Connector;

final readonly class ConnectorPolicy
{
    public function __construct(
        public ConnectorComplianceStatus $complianceStatus,
        public ?\DateTimeImmutable $reviewedAt = null,
        public ?string $note = null,
        public ?int $maxRequestsPerSync = null,
        public ?int $dailyQuota = null,
        public int $minimumDelayMilliseconds = 0,
        public bool $respectsRobotsTxt = false,
    ) {
        if ($this->maxRequestsPerSync !== null && $this->maxRequestsPerSync < 1) {
            throw new \InvalidArgumentException('Le nombre maximal de requêtes par synchronisation doit être positif.');
        }
        if ($this->dailyQuota !== null && $this->dailyQuota < 1) {
            throw new \InvalidArgumentException('Le quota journalier doit être positif.');
        }
        if ($this->minimumDelayMilliseconds < 0) {
            throw new \InvalidArgumentException('Le délai minimal entre requêtes ne peut pas être négatif.');
        }
    }

    public static function underReview(?string $note = null): self
    {
        return new self(
            ConnectorComplianceStatus::UNDER_REVIEW,
            note: $note ?? 'Aucune politique de collecte explicite n’est déclarée par ce connecteur.',
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'complianceStatus' => $this->complianceStatus->value,
            'complianceLabel' => $this->complianceStatus->label(),
            'collectionAllowed' => $this->complianceStatus->allowsAutomatedCollection(),
            'reviewedAt' => $this->reviewedAt?->format('Y-m-d'),
            'note' => $this->note,
            'maxRequestsPerSync' => $this->maxRequestsPerSync,
            'dailyQuota' => $this->dailyQuota,
            'minimumDelayMilliseconds' => $this->minimumDelayMilliseconds,
            'respectsRobotsTxt' => $this->respectsRobotsTxt,
        ];
    }
}
