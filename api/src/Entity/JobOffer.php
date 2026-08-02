<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(columns: ['published_at'], name: 'idx_job_published')]
#[ORM\Index(columns: ['score'], name: 'idx_job_score')]
class JobOffer
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 100)] private string $source = 'Manuel';
    #[ORM\Column(length: 500, nullable: true)] private ?string $sourceUrl = null;
    #[ORM\Column(length: 180, nullable: true)] private ?string $externalId = null;
    #[ORM\Column(length: 255)] private string $title = '';
    #[ORM\Column(length: 255)] private string $company = '';
    #[ORM\Column(length: 255, nullable: true)] private ?string $clientName = null;
    #[ORM\Column(length: 255)] private string $location = '';
    #[ORM\Column(length: 80)] private string $contractType = '';
    #[ORM\Column(length: 80)] private string $workMode = '';
    #[ORM\Column(length: 8)] private string $language = 'fr';
    #[ORM\Column(type: 'text')] private string $description = '';
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $publishedAt = null;
    #[ORM\Column] private \DateTimeImmutable $discoveredAt;
    #[ORM\Column(nullable: true)] private ?int $salaryMin = null;
    #[ORM\Column(nullable: true)] private ?int $salaryMax = null;
    #[ORM\Column(nullable: true)] private ?int $tjmFixed = null;
    #[ORM\Column(nullable: true)] private ?int $tjmMin = null;
    #[ORM\Column(nullable: true)] private ?int $tjmMax = null;
    #[ORM\Column(nullable: true)] private ?int $proposedTjm = null;
    #[ORM\Column(nullable: true)] private ?int $proposedSalary = null;
    #[ORM\Column] private int $score = 0;
    #[ORM\Column(type: 'json')] private array $scoreReasons = [];
    #[ORM\Column(length: 50)] private string $status = 'DISCOVERED';
    #[ORM\ManyToOne] #[ORM\JoinColumn(onDelete: 'SET NULL')] private ?CvDocument $recommendedCv = null;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $preparedAt = null;
    #[ORM\Column(type: 'json')] private array $rawData = [];

    public function __construct() { $this->discoveredAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getCompany(): string { return $this->company; }
    public function getClientName(): ?string { return $this->clientName; }
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

    public function fill(array $data): self
    {
        foreach (['source','sourceUrl','externalId','title','company','clientName','location','contractType','workMode','language','description','status'] as $field) {
            if (array_key_exists($field, $data)) $this->{$field} = $data[$field] === null ? null : trim((string) $data[$field]);
        }
        foreach (['salaryMin','salaryMax','tjmFixed','tjmMin','tjmMax'] as $field) {
            if (array_key_exists($field, $data)) $this->{$field} = $data[$field] === null || $data[$field] === '' ? null : (int) $data[$field];
        }
        if (array_key_exists('publishedAt', $data)) {
            $this->publishedAt = empty($data['publishedAt']) ? null : new \DateTimeImmutable((string) $data['publishedAt']);
        }
        if (array_key_exists('rawData', $data)) $this->rawData = is_array($data['rawData']) ? $data['rawData'] : [];
        return $this;
    }

    public function setEvaluation(string $language, int $score, array $reasons, ?int $proposedTjm, ?int $proposedSalary, string $status, ?CvDocument $cv): void
    {
        $this->language = $language;
        $this->score = max(0, min(100, $score));
        $this->scoreReasons = $reasons;
        $this->proposedTjm = $proposedTjm;
        $this->proposedSalary = $proposedSalary;
        $this->status = $status;
        $this->recommendedCv = $cv;
        if ($status === 'PREPARED') $this->preparedAt = new \DateTimeImmutable();
    }

    public function markPrepared(): void { $this->status = 'PREPARED'; $this->preparedAt = new \DateTimeImmutable(); }

    public function toArray(): array
    {
        $ageHours = $this->publishedAt ? max(0, (int) floor((time() - $this->publishedAt->getTimestamp()) / 3600)) : null;
        return [
            'id' => $this->id,
            'source' => $this->source,
            'sourceUrl' => $this->sourceUrl,
            'externalId' => $this->externalId,
            'title' => $this->title,
            'company' => $this->company,
            'clientName' => $this->clientName,
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
}
