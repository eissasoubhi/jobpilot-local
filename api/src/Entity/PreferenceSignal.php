<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(columns: ['signal_type', 'created_at'], name: 'idx_preference_signal_type_created')]
#[ORM\Index(columns: ['origin', 'created_at'], name: 'idx_preference_signal_origin_created')]
class PreferenceSignal
{
    public const ORIGIN_USER_DECISION = 'USER_DECISION';
    public const ORIGIN_PIPELINE_OUTCOME = 'PIPELINE_OUTCOME';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JobOffer $jobOffer;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Application $application;

    #[ORM\Column(length: 60)]
    private string $signalType;

    #[ORM\Column(type: 'smallint')]
    private int $preferenceValue;

    #[ORM\Column(length: 40)]
    private string $origin;

    #[ORM\Column(type: 'json')]
    private array $context = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @param array<string, mixed> $context */
    public function __construct(
        Application $application,
        string $signalType,
        int $preferenceValue,
        string $origin,
        array $context = [],
    ) {
        if (!in_array($preferenceValue, [-1, 0, 1], true)) {
            throw new \InvalidArgumentException('Preference value must be -1, 0 or 1.');
        }
        if (!in_array($origin, [self::ORIGIN_USER_DECISION, self::ORIGIN_PIPELINE_OUTCOME], true)) {
            throw new \InvalidArgumentException('Unknown preference signal origin.');
        }

        $this->application = $application;
        $this->jobOffer = $application->getJobOffer();
        $this->signalType = trim($signalType);
        $this->preferenceValue = $preferenceValue;
        $this->origin = $origin;
        $this->context = $context;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getJobOffer(): JobOffer { return $this->jobOffer; }
    public function getApplication(): Application { return $this->application; }
    public function getSignalType(): string { return $this->signalType; }
    public function getPreferenceValue(): int { return $this->preferenceValue; }
    public function getOrigin(): string { return $this->origin; }
    /** @return array<string, mixed> */
    public function getContext(): array { return $this->context; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'jobOfferId' => $this->jobOffer->getId(),
            'applicationId' => $this->application->getId(),
            'signalType' => $this->signalType,
            'preferenceValue' => $this->preferenceValue,
            'origin' => $this->origin,
            'context' => $this->context,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
