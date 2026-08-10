<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'job_source_occurrence')]
#[ORM\UniqueConstraint(name: 'uniq_job_occurrence_source_external', columns: ['source_code', 'external_id'])]
#[ORM\Index(columns: ['job_offer_id'], name: 'idx_job_occurrence_offer')]
#[ORM\Index(columns: ['normalized_url'], name: 'idx_job_occurrence_url')]
#[ORM\Index(columns: ['last_seen_at'], name: 'idx_job_occurrence_last_seen')]
final class JobSourceOccurrence
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'occurrences')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JobOffer $jobOffer;

    #[ORM\Column(length: 64)]
    private string $sourceCode;

    #[ORM\Column(length: 120)]
    private string $sourceName;

    #[ORM\Column(length: 180)]
    private string $externalId;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $normalizedUrl = null;

    #[ORM\Column(length: 32)]
    private string $matchType = 'PRIMARY';

    #[ORM\Column]
    private int $matchScore = 100;

    #[ORM\Column(type: 'json')]
    private array $matchReasons = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'json')]
    private array $rawData = [];

    #[ORM\Column]
    private \DateTimeImmutable $firstSeenAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    public function __construct(
        JobOffer $jobOffer,
        string $sourceCode,
        string $sourceName,
        string $externalId,
    ) {
        $sourceCode = strtolower(trim($sourceCode));
        $externalId = trim($externalId);
        if ($sourceCode === '' || $externalId === '') {
            throw new \InvalidArgumentException('La source et l’identifiant externe de l’occurrence sont obligatoires.');
        }

        $this->jobOffer = $jobOffer;
        $this->sourceCode = mb_substr($sourceCode, 0, 64);
        $this->sourceName = mb_substr(trim($sourceName) !== '' ? trim($sourceName) : $sourceCode, 0, 120);
        $this->externalId = mb_substr($externalId, 0, 180);
        $this->firstSeenAt = $this->lastSeenAt = new \DateTimeImmutable();
        $jobOffer->addOccurrence($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJobOffer(): JobOffer
    {
        return $this->jobOffer;
    }

    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function getNormalizedUrl(): ?string
    {
        return $this->normalizedUrl;
    }

    public function refresh(
        array $payload,
        string $matchType,
        int $matchScore,
        array $matchReasons = [],
    ): void {
        $sourceUrl = trim((string) ($payload['sourceUrl'] ?? ''));
        $normalizedUrl = trim((string) ($payload['normalizedUrl'] ?? ''));
        $this->sourceUrl = $sourceUrl === '' ? null : mb_substr($sourceUrl, 0, 500);
        $this->normalizedUrl = $normalizedUrl === '' ? null : mb_substr($normalizedUrl, 0, 500);
        $this->matchType = mb_substr(trim($matchType) !== '' ? trim($matchType) : 'PRIMARY', 0, 32);
        $this->matchScore = max(0, min(100, $matchScore));
        $this->matchReasons = array_values(array_map(
            static fn (mixed $reason): string => mb_substr(trim((string) $reason), 0, 300),
            array_filter($matchReasons, static fn (mixed $reason): bool => trim((string) $reason) !== ''),
        ));
        $this->publishedAt = $this->parseDate($payload['publishedAt'] ?? null);
        $this->rawData = is_array($payload['rawData'] ?? null) ? $payload['rawData'] : [];
        $this->lastSeenAt = new \DateTimeImmutable();
    }

    public function touch(array $payload = []): void
    {
        if ($payload !== []) {
            $sourceUrl = trim((string) ($payload['sourceUrl'] ?? ''));
            if ($this->sourceUrl === null && $sourceUrl !== '') {
                $this->sourceUrl = mb_substr($sourceUrl, 0, 500);
            }
            $normalizedUrl = trim((string) ($payload['normalizedUrl'] ?? ''));
            if ($this->normalizedUrl === null && $normalizedUrl !== '') {
                $this->normalizedUrl = mb_substr($normalizedUrl, 0, 500);
            }
            if ($this->publishedAt === null) {
                $this->publishedAt = $this->parseDate($payload['publishedAt'] ?? null);
            }

            $candidateRawData = is_array($payload['rawData'] ?? null) ? $payload['rawData'] : [];
            foreach (['alertPlatform', 'alertPlatformCode'] as $key) {
                $value = trim((string) ($candidateRawData[$key] ?? ''));
                if ($value !== '') {
                    $this->rawData[$key] = $value;
                }
            }
        }

        $this->lastSeenAt = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $originPlatformCode = $this->provenanceValue('alertPlatformCode');
        $originPlatformName = $this->provenanceValue('alertPlatform');

        return [
            'id' => $this->id,
            'sourceCode' => $this->sourceCode,
            'sourceName' => $originPlatformName !== null
                ? $originPlatformName.' via '.$this->sourceName
                : $this->sourceName,
            'connectorName' => $this->sourceName,
            'externalId' => $this->externalId,
            'sourceUrl' => $this->sourceUrl,
            'originPlatformCode' => $originPlatformCode,
            'originPlatformName' => $originPlatformName,
            'matchType' => $this->matchType,
            'matchScore' => $this->matchScore,
            'matchReasons' => $this->matchReasons,
            'publishedAt' => $this->publishedAt?->format(DATE_ATOM),
            'firstSeenAt' => $this->firstSeenAt->format(DATE_ATOM),
            'lastSeenAt' => $this->lastSeenAt->format(DATE_ATOM),
        ];
    }

    private function provenanceValue(string $key): ?string
    {
        $value = trim((string) ($this->rawData[$key] ?? ''));

        return $value === '' ? null : mb_substr($value, 0, 120);
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
