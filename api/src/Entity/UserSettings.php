<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class UserSettings
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 8)] private string $interfaceLanguage = 'fr';
    #[ORM\Column(type: 'json')] private array $targetJobs = [];
    #[ORM\Column(type: 'json')] private array $exclusions = [];
    #[ORM\Column(type: 'json')] private array $skills = [];
    #[ORM\Column] private int $matchingThreshold = 50;
    #[ORM\Column] private int $defaultIdfTjm = 500;
    #[ORM\Column] private int $defaultOutsideIdfTjm = 480;
    #[ORM\Column] private int $defaultRemoteTjm = 480;
    #[ORM\Column] private int $minimumFreelanceTjm = 300;
    #[ORM\Column] private int $maximumTjm = 520;
    #[ORM\Column] private int $minimumCdiSalary = 35000;
    #[ORM\Column] private bool $salaryIncludesTotalCompensation = true;
    #[ORM\Column(length: 120, nullable: true)] private ?string $cddSalaryRule = null;
    #[ORM\Column] private bool $autoPrepare = true;
    #[ORM\Column] private bool $autoSubmitEnabled = false;
    #[ORM\Column] private int $autoSubmitThreshold = 60;
    #[ORM\Column] private int $autoSubmitDailyLimit = 5;
    #[ORM\Column(length: 40)] private string $finalSubmissionMode = 'ONE_CLICK';
    #[ORM\Column] private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTargetJobs(): array { return $this->targetJobs; }
    public function getExclusions(): array { return $this->exclusions; }
    public function getSkills(): array { return $this->skills; }
    public function getMatchingThreshold(): int { return $this->matchingThreshold; }
    public function getDefaultIdfTjm(): int { return $this->defaultIdfTjm; }
    public function getDefaultOutsideIdfTjm(): int { return $this->defaultOutsideIdfTjm; }
    public function getDefaultRemoteTjm(): int { return $this->defaultRemoteTjm; }
    public function getMinimumFreelanceTjm(): int { return $this->minimumFreelanceTjm; }
    public function getMaximumTjm(): int { return $this->maximumTjm; }
    public function getMinimumCdiSalary(): int { return $this->minimumCdiSalary; }
    public function salaryIncludesTotalCompensation(): bool { return $this->salaryIncludesTotalCompensation; }
    public function isAutoPrepare(): bool { return $this->autoPrepare; }
    public function isAutoSubmitEnabled(): bool { return $this->autoSubmitEnabled; }
    public function getAutoSubmitThreshold(): int { return $this->autoSubmitThreshold; }
    public function getAutoSubmitDailyLimit(): int { return $this->autoSubmitDailyLimit; }

    public function fill(array $data): self
    {
        if (array_key_exists('interfaceLanguage', $data)) {
            $language = strtolower(trim((string) ($data['interfaceLanguage'] ?? 'fr')));
            $this->interfaceLanguage = in_array($language, ['fr', 'en'], true) ? $language : 'fr';
        }

        if (array_key_exists('finalSubmissionMode', $data)) {
            $value = trim((string) ($data['finalSubmissionMode'] ?? 'ONE_CLICK'));
            $this->finalSubmissionMode = $value === '' ? 'ONE_CLICK' : $value;
        }

        if (array_key_exists('cddSalaryRule', $data)) {
            $value = trim((string) ($data['cddSalaryRule'] ?? ''));
            $this->cddSalaryRule = $value === '' ? null : $value;
        }

        foreach (['targetJobs', 'exclusions', 'skills'] as $field) {
            if (array_key_exists($field, $data)) {
                $this->{$field} = is_array($data[$field])
                    ? array_values(array_filter(array_map(
                        static fn (mixed $value): string => trim((string) $value),
                        $data[$field],
                    )))
                    : [];
            }
        }

        foreach ([
            'matchingThreshold', 'defaultIdfTjm', 'defaultOutsideIdfTjm',
            'defaultRemoteTjm', 'minimumFreelanceTjm', 'maximumTjm', 'minimumCdiSalary',
            'autoSubmitThreshold', 'autoSubmitDailyLimit',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $this->{$field} = max(0, (int) $data[$field]);
            }
        }

        foreach (['salaryIncludesTotalCompensation', 'autoPrepare', 'autoSubmitEnabled'] as $field) {
            if (array_key_exists($field, $data)) {
                $this->{$field} = (bool) $data[$field];
            }
        }

        if ($this->minimumFreelanceTjm > $this->maximumTjm) {
            throw new \InvalidArgumentException('Le TJM minimum ne peut pas dépasser le TJM maximum.');
        }

        $this->matchingThreshold = min(100, $this->matchingThreshold);
        $this->autoSubmitThreshold = min(100, max(1, $this->autoSubmitThreshold));
        $this->autoSubmitDailyLimit = min(50, max(1, $this->autoSubmitDailyLimit));
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'interfaceLanguage' => $this->interfaceLanguage,
            'targetJobs' => $this->targetJobs,
            'exclusions' => $this->exclusions,
            'skills' => $this->skills,
            'matchingThreshold' => $this->matchingThreshold,
            'defaultIdfTjm' => $this->defaultIdfTjm,
            'defaultOutsideIdfTjm' => $this->defaultOutsideIdfTjm,
            'defaultRemoteTjm' => $this->defaultRemoteTjm,
            'minimumFreelanceTjm' => $this->minimumFreelanceTjm,
            'maximumTjm' => $this->maximumTjm,
            'minimumCdiSalary' => $this->minimumCdiSalary,
            'salaryIncludesTotalCompensation' => $this->salaryIncludesTotalCompensation,
            'cddSalaryRule' => $this->cddSalaryRule,
            'autoPrepare' => $this->autoPrepare,
            'autoSubmitEnabled' => $this->autoSubmitEnabled,
            'autoSubmitThreshold' => $this->autoSubmitThreshold,
            'autoSubmitDailyLimit' => $this->autoSubmitDailyLimit,
            'finalSubmissionMode' => $this->finalSubmissionMode,
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
