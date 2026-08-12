<?php

declare(strict_types=1);

namespace App\Entity;

use App\Timeline\JobTimelineEventType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'job_timeline_event')]
#[ORM\Index(columns: ['job_offer_id', 'occurred_at'], name: 'idx_job_timeline_offer_occurred')]
#[ORM\Index(columns: ['type', 'occurred_at'], name: 'idx_job_timeline_type_occurred')]
final class JobTimelineEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JobOffer $jobOffer;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Application $application;

    #[ORM\Column(length: 64)]
    private string $type;

    #[ORM\Column(length: 32)]
    private string $source;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column]
    private \DateTimeImmutable $recordedAt;

    /** @param array<string, mixed> $payload */
    public function __construct(
        JobOffer $jobOffer,
        string $type,
        array $payload = [],
        ?Application $application = null,
        ?\DateTimeImmutable $occurredAt = null,
        string $source = 'jobpilot',
    ) {
        if ($application !== null && $application->getJobOffer() !== $jobOffer) {
            throw new \InvalidArgumentException('La candidature liée à la timeline doit appartenir à la même offre.');
        }

        $source = trim($source);
        if ($source === '') {
            throw new \InvalidArgumentException('La source de l’événement timeline est obligatoire.');
        }

        $this->jobOffer = $jobOffer;
        $this->application = $application;
        $this->type = JobTimelineEventType::assertSupported($type);
        $this->source = mb_substr($source, 0, 32);
        $this->payload = $payload;
        $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
        $this->recordedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getJobOffer(): JobOffer { return $this->jobOffer; }
    public function getApplication(): ?Application { return $this->application; }
    public function getType(): string { return $this->type; }
    public function getSource(): string { return $this->source; }
    /** @return array<string, mixed> */
    public function getPayload(): array { return $this->payload; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function getRecordedAt(): \DateTimeImmutable { return $this->recordedAt; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'jobOfferId' => $this->jobOffer->getId(),
            'applicationId' => $this->application?->getId(),
            'type' => $this->type,
            'source' => $this->source,
            'payload' => $this->payload,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
            'recordedAt' => $this->recordedAt->format(DATE_ATOM),
        ];
    }
}
