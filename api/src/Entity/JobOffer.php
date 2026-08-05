<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_job_source_external', columns: ['source_code', 'external_id'])]
#[ORM\Index(columns: ['published_at'], name: 'idx_job_published')]
#[ORM\Index(columns: ['score'], name: 'idx_job_score')]
class JobOffer
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $source = 'Manuel';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sourceCode = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 255)]
    private string $company = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $applicationEmail = null;

    #[ORM\Column(length: 255)]
    private string $location = '';

    #[ORM\Column(length: 80)]
    private string $contractType = '';

    #[ORM\Column(length: 80)]
    private string $workMode = '';

    #[ORM\Column(length: 8)]
    private string $language = 'fr';

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $discoveredAt;

    #[ORM\Column(nullable: true)]
    private ?int $salaryMin = null;

    #[ORM\Column(nullable: true)]
    private ?int $salaryMax = null;

    #[ORM\Column(nullable: true)]
    private ?int $tjmFixed = null;

    #[ORM\Column(nullable: true)]
    private ?int $tjmMin = null;

    #[ORM\Column(nullable: true)]
    private ?int $tjmMax = null;

    #[ORM\Column(nullable: true)]
    private ?int $proposedTjm = null;

    #[ORM\Column(nullable: true)]
    private ?int $proposedSalary = null;

    #[ORM\Column]
    private int $score = 0;

    #[ORM\Column(type: 'json')]
    private array $scoreReasons = [];

    #[ORM\Column(length: 50)]
    private string $status = 'DISCOVERED';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?CvDocument $recommendedCv = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $preparedAt = null;

    #[ORM\Column(type: 'json')]
    private array $rawData = [];

    /** @var Collection<int, JobSourceOccurrence> */
    #[ORM\OneToMany(mappedBy: 'jobOffer', targetEntity: JobSourceOccurrence::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['firstSeenAt' => 'ASC'])]
    private Collection $occurrences;

    public function __construct()
    {
        $this->discoveredAt = new \DateTimeImmutable();
        $this->occurrences = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getSource(): string { return $this->source; }
    public function getSourceCode(): ?string { return $this->sourceCode; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function getExternalId(): ?string { return $this->externalId; }
    public function getTitle(): string { return $this->title; }
    public function getCompany(): string { return $this->company; }
    public function getClientName(): ?string { return $this->clientName; }
    public function getApplicationEmail(): ?string { return $this->applicationEmail; }
    public function getLocation(): string { return $this->location; }
    public function getContractType(): string { return $this->contractType; }
    public function getWorkMode(): string { return $this->workMode; }
    public function getLanguage(): string { return $this->language; }
    public function getDescription(): string { return $this->description; }
    public function getTjmFixed(): ?int { return $this->tjmFixed; }
    public function getTjmMin(): ?int { return $this->tjmMin; }
    public function getTjmMax(): ?int { return $this->tjmMax; }
    public function getSalaryMin(): ?int { return $this->salaryMin; }
    public function getSalaryMax(): ?int { return $this->salaryMax; }
    public function getProposedTjm(): ?int { return $this->proposedTjm; }
    public function getProposedSalary(): ?int { return $this->proposedSalary; }
    public function getScore(): int { return $this->score; }
    public function getStatus(): string { return $this->status; }
    public function getRecommendedCv(): ?CvDocument { return $this->recommendedCv; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function getDiscoveredAt(): \DateTimeImmutable { return $this->discoveredAt; }

    /** @return Collection<int, JobSourceOccurrence> */
    public function getOccurrences(): Collection
    {
        return $this->occurrences;
    }

    public function addOccurrence(JobSourceOccurrence $occurrence): void
    {
        if (!$this->occurrences->contains($occurrence)) {
            $this->occurrences->add($occurrence);
        }
    }

    public function fill(array $data): self
    {
        foreach (['source', 'title', 'company', 'location', 'contractType', 'workMode', 'language', 'description', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $this->{$field} = trim((string) ($data[$field] ?? ''));
            }
        }

        foreach (['sourceCode', 'sourceUrl', 'externalId', 'clientName'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) ($data[$field] ?? ''));
                $this->{$field} = $value === '' ? null : $value;
            }
        }

        if ($this->sourceCode !== null) {
            $this->sourceCode = strtolower($this->sourceCode);
        }

        if (array_key_exists('applicationEmail', $data)) {
            $this->setApplicationEmail(trim((string) ($data['applicationEmail'] ?? '')) ?: null);
        }

        foreach (['salaryMin', 'salaryMax', 'tjmFixed', 'tjmMin', 'tjmMax'] as $field) {
            if (array_key_exists($field, $data)) {
                $this->{$field} = $data[$field] === null || $data[$field] === ''
                    ? null
                    : max(0, (int) $data[$field]);
            }
        }

        if (array_key_exists('publishedAt', $data)) {
            $this->publishedAt = $this->parseDate($data['publishedAt'], 'Date de publication invalide.');
        }

        if (array_key_exists('rawData', $data)) {
            $this->rawData = is_array($data['rawData']) ? $data['rawData'] : [];
        }

        return $this;
    }

    /**
     * Enrichit l’offre canonique sans écraser une information déjà validée.
     *
     * @return bool true lorsqu’au moins un champ a été complété
     */
    public function enrichFromOccurrence(array $data): bool
    {
        $changed = false;

        foreach (['company', 'location', 'contractType', 'workMode'] as $field) {
            $candidate = trim((string) ($data[$field] ?? ''));
            if ($this->{$field} === '' && $candidate !== '') {
                $this->{$field} = $candidate;
                $changed = true;
            }
        }

        $candidateClient = trim((string) ($data['clientName'] ?? ''));
        if ($this->clientName === null && $candidateClient !== '') {
            $this->clientName = $candidateClient;
            $changed = true;
        }

        $candidateDescription = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($this->description) < 180 && mb_strlen($candidateDescription) > mb_strlen($this->description)) {
            $this->description = $candidateDescription;
            $changed = true;
        }

        $candidateUrl = trim((string) ($data['sourceUrl'] ?? ''));
        if ($this->sourceUrl === null && $candidateUrl !== '') {
            $this->sourceUrl = $candidateUrl;
            $changed = true;
        }

        $candidateEmail = trim((string) ($data['applicationEmail'] ?? ''));
        if ($this->applicationEmail === null && $candidateEmail !== '' && filter_var($candidateEmail, FILTER_VALIDATE_EMAIL) !== false) {
            $this->applicationEmail = mb_strtolower($candidateEmail);
            $changed = true;
        }

        foreach (['salaryMin', 'salaryMax', 'tjmFixed', 'tjmMin', 'tjmMax'] as $field) {
            if ($this->{$field} === null && isset($data[$field]) && $data[$field] !== '') {
                $this->{$field} = max(0, (int) $data[$field]);
                $changed = true;
            }
        }

        if (isset($data['publishedAt']) && $data['publishedAt'] !== '') {
            $candidatePublishedAt = $this->parseDate($data['publishedAt'], 'Date de publication invalide.');
            if ($candidatePublishedAt !== null && ($this->publishedAt === null || $candidatePublishedAt < $this->publishedAt)) {
                $this->publishedAt = $candidatePublishedAt;
                $changed = true;
            }
        }

        return $changed;
    }

    public function setApplicationEmail(?string $email): void
    {
        if ($email === null || $email === '') {
            $this->applicationEmail = null;
            return;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Adresse e-mail de candidature invalide.');
        }

        $this->applicationEmail = mb_strtolower($email);
    }

    public function setEvaluation(
        string $language,
        int $score,
        array $reasons,
        ?int $proposedTjm,
        ?int $proposedSalary,
        string $status,
        ?CvDocument $cv,
    ): void {
        $this->language = $language;
        $this->score = max(0, min(100, $score));
        $this->scoreReasons = array_values($reasons);
        $this->proposedTjm = $proposedTjm;
        $this->proposedSalary = $proposedSalary;
        $this->status = $status;
        $this->recommendedCv = $cv;
        $this->preparedAt = $status === 'PREPARED' ? new \DateTimeImmutable() : null;
    }

    public function markPrepared(): void
    {
        $this->status = 'PREPARED';
        $this->preparedAt = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $ageHours = $this->publishedAt
            ? max(0, (int) floor((time() - $this->publishedAt->getTimestamp()) / 3600))
            : null;

        $sources = array_map(
            static fn (JobSourceOccurrence $occurrence): array => $occurrence->toArray(),
            $this->occurrences->toArray(),
        );
        if ($sources === []) {
            $sources[] = [
                'id' => null,
                'sourceCode' => $this->sourceCode ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $this->source) ?? 'manual'),
                'sourceName' => $this->source,
                'externalId' => $this->externalId,
                'sourceUrl' => $this->sourceUrl,
                'matchType' => 'LEGACY',
                'matchScore' => 100,
                'matchReasons' => [],
                'publishedAt' => $this->publishedAt?->format(DATE_ATOM),
                'firstSeenAt' => $this->discoveredAt->format(DATE_ATOM),
                'lastSeenAt' => $this->discoveredAt->format(DATE_ATOM),
            ];
        }

        return [
            'id' => $this->id,
            'source' => $this->source,
            'sourceCode' => $this->sourceCode,
            'sourceUrl' => $this->sourceUrl,
            'externalId' => $this->externalId,
            'sources' => $sources,
            'sourceCount' => count($sources),
            'title' => $this->title,
            'company' => $this->company,
            'clientName' => $this->clientName,
            'applicationEmail' => $this->applicationEmail,
            'location' => $this->location,
            'contractType' => $this->contractType,
            'workMode' => $this->workMode,
            'language' => $this->language,
            'description' => $this->description,
            'publishedAt' => $this->publishedAt?->format(DATE_ATOM),
            'discoveredAt' => $this->discoveredAt->format(DATE_ATOM),
            'ageHours' => $ageHours,
            'salaryMin' => $this->salaryMin,
            'salaryMax' => $this->salaryMax,
            'tjmFixed' => $this->tjmFixed,
            'tjmMin' => $this->tjmMin,
            'tjmMax' => $this->tjmMax,
            'proposedTjm' => $this->proposedTjm,
            'proposedSalary' => $this->proposedSalary,
            'score' => $this->score,
            'scoreReasons' => $this->scoreReasons,
            'status' => $this->status,
            'recommendedCv' => $this->recommendedCv?->toArray(),
            'preparedAt' => $this->preparedAt?->format(DATE_ATOM),
        ];
    }

    private function parseDate(mixed $value, string $message): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Exception) {
            throw new \InvalidArgumentException($message);
        }
    }
}
