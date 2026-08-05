<?php

declare(strict_types=1);

namespace App\Entity;

use App\JobDiscovery\Domain\Connector\ConnectorComplianceStatus;
use App\JobDiscovery\Domain\Connector\ConnectorPolicy;
use App\JobDiscovery\Domain\Connector\GovernedJobSourceConnector;
use App\JobDiscovery\Domain\Connector\JobSourceConnector;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'source_connector')]
#[ORM\UniqueConstraint(name: 'uniq_source_connector_code', columns: ['code'])]
#[ORM\Index(columns: ['status'], name: 'idx_source_connector_status')]
#[ORM\Index(columns: ['compliance_status'], name: 'idx_source_connector_compliance')]
final class SourceConnector
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $code;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 32)]
    private string $mode;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private bool $configured = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $configurationMessage = null;

    #[ORM\Column(length: 32, options: ['default' => 'UNDER_REVIEW'])]
    private string $complianceStatus = 'UNDER_REVIEW';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $complianceReviewedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $complianceNote = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxRequestsPerSync = null;

    #[ORM\Column(nullable: true)]
    private ?int $dailyQuota = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $minimumDelayMilliseconds = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $respectsRobotsTxt = false;

    #[ORM\Column(length: 32)]
    private string $status = 'NEVER_SYNCED';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSuccessfulAt = null;

    #[ORM\Column]
    private int $lastReceived = 0;

    #[ORM\Column]
    private int $lastImported = 0;

    #[ORM\Column]
    private int $lastMerged = 0;

    #[ORM\Column]
    private int $lastDuplicates = 0;

    #[ORM\Column]
    private int $lastFailed = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(JobSourceConnector $connector)
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->code = strtolower(trim($connector->code()));
        $this->name = trim($connector->name());
        $this->mode = $connector->mode()->value;
        $this->refreshDefinition($connector);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function isCollectionAllowed(): bool
    {
        return ConnectorComplianceStatus::tryFrom($this->complianceStatus)?->allowsAutomatedCollection() ?? false;
    }

    public function canSynchronize(): bool
    {
        return $this->enabled && $this->configured && $this->isCollectionAllowed();
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function isDue(int $intervalSeconds): bool
    {
        if (!$this->canSynchronize()) {
            return false;
        }

        $nextSyncAt = $this->lastSyncedAt?->modify(sprintf('+%d seconds', max(900, $intervalSeconds)));

        return $nextSyncAt === null || $nextSyncAt <= new \DateTimeImmutable();
    }

    public function refreshDefinition(JobSourceConnector $connector): void
    {
        $this->name = trim($connector->name());
        $this->mode = $connector->mode()->value;
        $this->configured = $connector->isConfigured();
        $message = trim((string) $connector->configurationMessage());
        $this->configurationMessage = $message === '' ? null : $message;
        $this->applyPolicy(
            $connector instanceof GovernedJobSourceConnector
                ? $connector->policy()
                : ConnectorPolicy::underReview(),
        );

        $this->status = $this->resolvedIdleStatus();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        $this->status = $this->resolvedIdleStatus();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markRunning(): void
    {
        if (!$this->canSynchronize()) {
            throw new \LogicException(sprintf('Le connecteur %s ne peut pas être synchronisé dans son état actuel.', $this->code));
        }

        $this->status = 'RUNNING';
        $this->lastError = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function complete(
        int $received,
        int $imported,
        int $merged,
        int $duplicates,
        int $failed,
        ?string $error = null,
    ): void {
        $now = new \DateTimeImmutable();
        $this->lastSyncedAt = $now;
        $this->lastReceived = max(0, $received);
        $this->lastImported = max(0, $imported);
        $this->lastMerged = max(0, $merged);
        $this->lastDuplicates = max(0, $duplicates);
        $this->lastFailed = max(0, $failed);
        $this->lastError = $error !== null && trim($error) !== '' ? trim($error) : null;
        $this->status = match (true) {
            $this->lastError !== null && $received === 0 => 'ERROR',
            $failed > 0 || $this->lastError !== null => 'PARTIAL',
            default => 'SUCCESS',
        };
        if ($this->status === 'SUCCESS') {
            $this->lastSuccessfulAt = $now;
        }
        $this->updatedAt = $now;
    }

    /** @return array<string, mixed> */
    public function toArray(int $intervalSeconds): array
    {
        $nextSyncAt = $this->lastSyncedAt?->modify(sprintf('+%d seconds', max(900, $intervalSeconds)));
        $policy = new ConnectorPolicy(
            ConnectorComplianceStatus::tryFrom($this->complianceStatus) ?? ConnectorComplianceStatus::UNDER_REVIEW,
            $this->complianceReviewedAt,
            $this->complianceNote,
            $this->maxRequestsPerSync,
            $this->dailyQuota,
            $this->minimumDelayMilliseconds,
            $this->respectsRobotsTxt,
        );

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'mode' => $this->mode,
            'enabled' => $this->enabled,
            'configured' => $this->configured,
            'configurationMessage' => $this->configurationMessage,
            'collectionAllowed' => $this->isCollectionAllowed(),
            'policy' => $policy->toArray(),
            'status' => $this->status,
            'lastSyncedAt' => $this->lastSyncedAt?->format(DATE_ATOM),
            'lastSuccessfulAt' => $this->lastSuccessfulAt?->format(DATE_ATOM),
            'nextSyncAt' => $nextSyncAt?->format(DATE_ATOM),
            'due' => $this->isDue($intervalSeconds),
            'lastResult' => [
                'received' => $this->lastReceived,
                'imported' => $this->lastImported,
                'merged' => $this->lastMerged,
                'duplicates' => $this->lastDuplicates,
                'failed' => $this->lastFailed,
            ],
            'lastError' => $this->lastError,
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    private function applyPolicy(ConnectorPolicy $policy): void
    {
        $this->complianceStatus = $policy->complianceStatus->value;
        $this->complianceReviewedAt = $policy->reviewedAt;
        $note = trim((string) $policy->note);
        $this->complianceNote = $note === '' ? null : $note;
        $this->maxRequestsPerSync = $policy->maxRequestsPerSync;
        $this->dailyQuota = $policy->dailyQuota;
        $this->minimumDelayMilliseconds = $policy->minimumDelayMilliseconds;
        $this->respectsRobotsTxt = $policy->respectsRobotsTxt;
    }

    private function resolvedIdleStatus(): string
    {
        return match (true) {
            !$this->enabled => 'DISABLED',
            !$this->configured => 'MISCONFIGURED',
            !$this->isCollectionAllowed() => 'COMPLIANCE_BLOCKED',
            $this->lastSyncedAt === null => 'NEVER_SYNCED',
            default => 'READY',
        };
    }
}
