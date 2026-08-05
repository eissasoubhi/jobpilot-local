<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'connector_sync_run')]
#[ORM\Index(columns: ['started_at'], name: 'idx_connector_sync_started')]
#[ORM\Index(columns: ['status'], name: 'idx_connector_sync_status')]
final class ConnectorSyncRun
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SourceConnector $connector;

    #[ORM\Column(length: 24)]
    private string $trigger;

    #[ORM\Column(length: 24)]
    private string $status = 'RUNNING';

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column]
    private int $received = 0;

    #[ORM\Column]
    private int $imported = 0;

    #[ORM\Column]
    private int $merged = 0;

    #[ORM\Column]
    private int $duplicates = 0;

    #[ORM\Column]
    private int $failed = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: 'json')]
    private array $details = [];

    public function __construct(SourceConnector $connector, string $trigger)
    {
        $this->connector = $connector;
        $this->trigger = trim($trigger) !== '' ? trim($trigger) : 'manual';
        $this->startedAt = new \DateTimeImmutable();
    }

    public function complete(
        int $received,
        int $imported,
        int $merged,
        int $duplicates,
        int $failed,
        ?string $error = null,
        array $details = [],
    ): void {
        $this->received = max(0, $received);
        $this->imported = max(0, $imported);
        $this->merged = max(0, $merged);
        $this->duplicates = max(0, $duplicates);
        $this->failed = max(0, $failed);
        $this->error = $error !== null && trim($error) !== '' ? trim($error) : null;
        $this->details = $details;
        $this->status = match (true) {
            $this->error !== null && $received === 0 => 'FAILED',
            $failed > 0 || $this->error !== null => 'PARTIAL',
            default => 'SUCCEEDED',
        };
        $this->finishedAt = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $durationMs = $this->finishedAt === null
            ? null
            : max(0, (int) round(((float) $this->finishedAt->format('U.u') - (float) $this->startedAt->format('U.u')) * 1000));

        return [
            'id' => $this->id,
            'connector' => [
                'code' => $this->connector->getCode(),
                'name' => $this->connector->getName(),
            ],
            'trigger' => $this->trigger,
            'status' => $this->status,
            'startedAt' => $this->startedAt->format(DATE_ATOM),
            'finishedAt' => $this->finishedAt?->format(DATE_ATOM),
            'durationMs' => $durationMs,
            'received' => $this->received,
            'imported' => $this->imported,
            'merged' => $this->merged,
            'duplicates' => $this->duplicates,
            'failed' => $this->failed,
            'error' => $this->error,
            'details' => $this->details,
        ];
    }
}
