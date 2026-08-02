<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Positioning
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne] private ?JobOffer $jobOffer = null;
    #[ORM\Column(length: 255)] private string $finalClient = '';
    #[ORM\Column(length: 255)] private string $agency = '';
    #[ORM\Column(length: 180)] private string $recruiterName = '';
    #[ORM\Column(length: 180, nullable: true)] private ?string $recruiterEmail = null;
    #[ORM\Column(length: 50, nullable: true)] private ?string $recruiterPhone = null;
    #[ORM\Column(length: 255)] private string $missionTitle = '';
    #[ORM\Column(type: 'text')] private string $description = '';
    #[ORM\Column(length: 180, nullable: true)] private ?string $callForTenderReference = null;
    #[ORM\Column(nullable: true)] private ?int $advertisedTjmMin = null;
    #[ORM\Column(nullable: true)] private ?int $advertisedTjmMax = null;
    #[ORM\Column(nullable: true)] private ?int $advertisedTjmFixed = null;
    #[ORM\Column(nullable: true)] private ?int $proposedTjm = null;
    #[ORM\Column(nullable: true)] private ?int $acceptedTjm = null;
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $startDate = null;
    #[ORM\Column(length: 255)] private string $location = '';
    #[ORM\Column(length: 180)] private string $remotePolicy = '';
    #[ORM\Column(nullable: true)] private ?\DateTimeImmutable $agreementGivenAt = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $proofEmailId = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(onDelete: 'SET NULL')] private ?CvDocument $cvDocument = null;
    #[ORM\Column(length: 50)] private string $status = 'MISSION_DETECTED';
    #[ORM\Column] private \DateTimeImmutable $createdAt;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getFinalClient(): string { return $this->finalClient; }
    public function getMissionTitle(): string { return $this->missionTitle; }
    public function getDescription(): string { return $this->description; }
    public function getCallForTenderReference(): ?string { return $this->callForTenderReference; }
    public function getStatus(): string { return $this->status; }

    public function fill(array $data, ?JobOffer $job = null, ?CvDocument $cv = null): self
    {
        if ($job !== null) {
            $this->jobOffer = $job;
        }
        if ($cv !== null) {
            $this->cvDocument = $cv;
        }

        foreach (['finalClient', 'agency', 'recruiterName', 'missionTitle', 'description', 'location', 'remotePolicy', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $this->{$field} = trim((string) ($data[$field] ?? ''));
            }
        }

        foreach (['recruiterEmail', 'recruiterPhone', 'callForTenderReference', 'proofEmailId'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) ($data[$field] ?? ''));
                $this->{$field} = $value === '' ? null : $value;
            }
        }

        foreach (['advertisedTjmMin', 'advertisedTjmMax', 'advertisedTjmFixed', 'proposedTjm', 'acceptedTjm'] as $field) {
            if (array_key_exists($field, $data)) {
                $this->{$field} = $data[$field] === null || $data[$field] === ''
                    ? null
                    : max(0, (int) $data[$field]);
            }
        }

        if (array_key_exists('startDate', $data)) {
            $this->startDate = $this->parseDate($data['startDate'], 'Date de démarrage invalide.');
        }
        if (array_key_exists('agreementGivenAt', $data)) {
            $this->agreementGivenAt = $this->parseDate($data['agreementGivenAt'], 'Date d’accord invalide.');
        }

        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function toArray(): array
    {
        $rate = $this->acceptedTjm ?? $this->proposedTjm;
        $subject = 'Confirmation de positionnement - '.$this->missionTitle;
        $body = 'Bonjour'.($this->recruiterName !== '' ? ' '.$this->recruiterName : '').",\n\n";
        $body .= "Je vous confirme mon accord pour être positionné sur la mission \"{$this->missionTitle}\" auprès de {$this->finalClient}";
        if ($rate !== null) {
            $body .= " pour un TJM de {$rate} € HT/jour";
        }
        $body .= ".\n\n";

        if ($this->callForTenderReference !== null) {
            $body .= "Référence de l'appel d'offres : {$this->callForTenderReference}.\n\n";
        } else {
            $body .= "Merci de me confirmer la référence de l'appel d'offres, si elle est disponible.\n\n";
        }

        $body .= 'Bien cordialement';

        return [
            'id' => $this->id,
            'jobOfferId' => $this->jobOffer?->getId(),
            'finalClient' => $this->finalClient,
            'agency' => $this->agency,
            'recruiterName' => $this->recruiterName,
            'recruiterEmail' => $this->recruiterEmail,
            'recruiterPhone' => $this->recruiterPhone,
            'missionTitle' => $this->missionTitle,
            'description' => $this->description,
            'callForTenderReference' => $this->callForTenderReference,
            'advertisedTjmMin' => $this->advertisedTjmMin,
            'advertisedTjmMax' => $this->advertisedTjmMax,
            'advertisedTjmFixed' => $this->advertisedTjmFixed,
            'proposedTjm' => $this->proposedTjm,
            'acceptedTjm' => $this->acceptedTjm,
            'startDate' => $this->startDate?->format('Y-m-d'),
            'location' => $this->location,
            'remotePolicy' => $this->remotePolicy,
            'agreementGivenAt' => $this->agreementGivenAt?->format(DATE_ATOM),
            'proofEmailId' => $this->proofEmailId,
            'cvDocument' => $this->cvDocument?->toArray(),
            'status' => $this->status,
            'agreementEmailSubject' => $subject,
            'agreementEmailBody' => $body,
            'mailtoUrl' => $this->recruiterEmail
                ? 'mailto:'.$this->recruiterEmail.'?'.http_build_query(['subject' => $subject, 'body' => $body], '', '&', PHP_QUERY_RFC3986)
                : null,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
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
