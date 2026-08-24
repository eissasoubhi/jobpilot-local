<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'job_offer_matching_score_state')]
class JobOfferMatchingScoreState
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private JobOffer $jobOffer;

    #[ORM\Column]
    private int $version = 0;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(JobOffer $jobOffer, int $version = 0)
    {
        $this->jobOffer = $jobOffer;
        $this->version = max(0, $version);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getJobOffer(): JobOffer
    {
        return $this->jobOffer;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function markVersion(int $version): void
    {
        $this->version = max(0, $version);
        $this->updatedAt = new \DateTimeImmutable();
    }
}
