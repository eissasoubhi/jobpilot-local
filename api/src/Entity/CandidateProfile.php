<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CandidateProfile
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)] private string $fullName = '';
    #[ORM\Column(length: 180)] private string $email = '';
    #[ORM\Column(length: 40)] private string $phone = '';
    #[ORM\Column(length: 120)] private string $city = '';
    #[ORM\Column(length: 20)] private string $postalCode = '';
    #[ORM\Column(length: 180)] private string $mobility = '';
    #[ORM\Column(length: 120)] private string $workAuthorisation = '';
    #[ORM\Column(length: 120)] private string $availability = '';
    #[ORM\Column(length: 120)] private string $noticePeriod = '';
    #[ORM\Column] private int $yearsOfExperience = 0;
    #[ORM\Column(type: 'json')] private array $languages = [];
    #[ORM\Column(type: 'json')] private array $acceptedContracts = [];
    #[ORM\Column(length: 180)] private string $workModePreference = '';
    #[ORM\Column(length: 255, nullable: true)] private ?string $linkedinUrl = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $portfolioUrl = null;
    #[ORM\Column] private \DateTimeImmutable $updatedAt;

    public function __construct() { $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }

    public function fill(array $data): self
    {
        foreach ([
            'fullName','email','phone','city','postalCode','mobility','workAuthorisation',
            'availability','noticePeriod','workModePreference','linkedinUrl','portfolioUrl'
        ] as $field) {
            if (array_key_exists($field, $data)) { $this->{$field} = $data[$field] === null ? null : trim((string) $data[$field]); }
        }
        if (array_key_exists('yearsOfExperience', $data)) { $this->yearsOfExperience = max(0, (int) $data['yearsOfExperience']); }
        if (array_key_exists('languages', $data)) { $this->languages = is_array($data['languages']) ? $data['languages'] : []; }
        if (array_key_exists('acceptedContracts', $data)) { $this->acceptedContracts = is_array($data['acceptedContracts']) ? $data['acceptedContracts'] : []; }
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'fullName' => $this->fullName,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'postalCode' => $this->postalCode,
            'mobility' => $this->mobility,
            'workAuthorisation' => $this->workAuthorisation,
            'availability' => $this->availability,
            'noticePeriod' => $this->noticePeriod,
            'yearsOfExperience' => $this->yearsOfExperience,
            'languages' => $this->languages,
            'acceptedContracts' => $this->acceptedContracts,
            'workModePreference' => $this->workModePreference,
            'linkedinUrl' => $this->linkedinUrl,
            'portfolioUrl' => $this->portfolioUrl,
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
