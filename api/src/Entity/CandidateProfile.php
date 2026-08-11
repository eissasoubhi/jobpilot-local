<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CandidateProfile
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)] private string $fullName = '';
    #[ORM\Column(length: 120)] private string $firstName = '';
    #[ORM\Column(length: 120)] private string $lastName = '';
    #[ORM\Column(length: 180)] private string $email = '';
    #[ORM\Column(length: 40)] private string $phone = '';
    #[ORM\Column(length: 255)] private string $addressLine1 = '';
    #[ORM\Column(length: 255, nullable: true)] private ?string $addressLine2 = null;
    #[ORM\Column(length: 120)] private string $city = '';
    #[ORM\Column(length: 20)] private string $postalCode = '';
    #[ORM\Column(length: 120)] private string $region = '';
    #[ORM\Column(length: 120)] private string $country = '';
    #[ORM\Column(length: 2)] private string $countryCode = '';
    #[ORM\Column(length: 180)] private string $currentJobTitle = '';
    #[ORM\Column(length: 180)] private string $mobility = '';
    #[ORM\Column(type: 'json')] private array $preferredLocations = [];
    #[ORM\Column(length: 120)] private string $workAuthorisation = '';
    #[ORM\Column(length: 120)] private string $availability = '';
    #[ORM\Column(length: 120)] private string $noticePeriod = '';
    #[ORM\Column] private int $yearsOfExperience = 0;
    #[ORM\Column(type: 'json')] private array $technologyExperience = [];
    #[ORM\Column(type: 'json')] private array $languages = [];
    #[ORM\Column(type: 'json')] private array $acceptedContracts = [];
    #[ORM\Column(length: 180)] private string $workModePreference = '';
    #[ORM\Column(nullable: true)] private ?int $desiredSalary = null;
    #[ORM\Column(nullable: true)] private ?int $desiredTjm = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $linkedinUrl = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $githubUrl = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $portfolioUrl = null;
    #[ORM\Column(type: 'json')] private array $professionalUrls = [];
    #[ORM\Column] private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function fill(array $data): self
    {
        foreach ([
            'fullName', 'firstName', 'lastName', 'email', 'phone', 'addressLine1', 'city', 'postalCode',
            'region', 'country', 'countryCode', 'currentJobTitle', 'mobility', 'workAuthorisation',
            'availability', 'noticePeriod', 'workModePreference',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $this->{$field} = trim((string) ($data[$field] ?? ''));
            }
        }

        foreach (['addressLine2', 'linkedinUrl', 'githubUrl', 'portfolioUrl'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) ($data[$field] ?? ''));
                $this->{$field} = $value === '' ? null : $value;
            }
        }

        if ((array_key_exists('firstName', $data) || array_key_exists('lastName', $data))
            && ($this->firstName !== '' || $this->lastName !== '')) {
            $this->fullName = trim($this->firstName.' '.$this->lastName);
        }

        if (array_key_exists('yearsOfExperience', $data)) {
            $this->yearsOfExperience = max(0, (int) $data['yearsOfExperience']);
        }

        foreach (['desiredSalary', 'desiredTjm'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                $this->{$field} = $value === null || $value === '' ? null : max(0, (int) $value);
            }
        }

        foreach (['languages', 'preferredLocations', 'professionalUrls'] as $field) {
            if (array_key_exists($field, $data)) {
                $this->{$field} = is_array($data[$field]) ? array_values($data[$field]) : [];
            }
        }

        if (array_key_exists('technologyExperience', $data)) {
            $this->technologyExperience = [];
            if (is_array($data['technologyExperience'])) {
                foreach ($data['technologyExperience'] as $technology => $years) {
                    $technology = trim((string) $technology);
                    if ($technology !== '') {
                        $this->technologyExperience[$technology] = max(0, (int) $years);
                    }
                }
            }
        }

        if (array_key_exists('acceptedContracts', $data)) {
            $this->acceptedContracts = is_array($data['acceptedContracts'])
                ? array_values(array_filter(array_map('strval', $data['acceptedContracts'])))
                : [];
        }

        $this->countryCode = strtoupper(substr($this->countryCode, 0, 2));
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'fullName' => $this->fullName,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'addressLine1' => $this->addressLine1,
            'addressLine2' => $this->addressLine2,
            'city' => $this->city,
            'postalCode' => $this->postalCode,
            'region' => $this->region,
            'country' => $this->country,
            'countryCode' => $this->countryCode,
            'currentJobTitle' => $this->currentJobTitle,
            'mobility' => $this->mobility,
            'preferredLocations' => $this->preferredLocations,
            'workAuthorisation' => $this->workAuthorisation,
            'availability' => $this->availability,
            'noticePeriod' => $this->noticePeriod,
            'yearsOfExperience' => $this->yearsOfExperience,
            'technologyExperience' => $this->technologyExperience,
            'languages' => $this->languages,
            'acceptedContracts' => $this->acceptedContracts,
            'workModePreference' => $this->workModePreference,
            'desiredSalary' => $this->desiredSalary,
            'desiredTjm' => $this->desiredTjm,
            'linkedinUrl' => $this->linkedinUrl,
            'githubUrl' => $this->githubUrl,
            'portfolioUrl' => $this->portfolioUrl,
            'professionalUrls' => $this->professionalUrls,
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    public function toAutofillArray(): array
    {
        return [
            'schemaVersion' => 1,
            'identity' => [
                'fullName' => $this->fullName,
                'firstName' => $this->firstName,
                'lastName' => $this->lastName,
                'email' => $this->email,
                'phone' => $this->phone,
            ],
            'address' => [
                'line1' => $this->addressLine1,
                'line2' => $this->addressLine2,
                'city' => $this->city,
                'postalCode' => $this->postalCode,
                'region' => $this->region,
                'country' => $this->country,
                'countryCode' => $this->countryCode,
            ],
            'professional' => [
                'currentJobTitle' => $this->currentJobTitle,
                'yearsOfExperience' => $this->yearsOfExperience,
                'technologyExperience' => $this->technologyExperience,
                'languages' => $this->languages,
                'linkedinUrl' => $this->linkedinUrl,
                'githubUrl' => $this->githubUrl,
                'portfolioUrl' => $this->portfolioUrl,
                'otherUrls' => $this->professionalUrls,
            ],
            'preferences' => [
                'mobility' => $this->mobility,
                'preferredLocations' => $this->preferredLocations,
                'acceptedContracts' => $this->acceptedContracts,
                'workModePreference' => $this->workModePreference,
                'availability' => $this->availability,
                'noticePeriod' => $this->noticePeriod,
                'desiredSalary' => $this->desiredSalary,
                'desiredTjm' => $this->desiredTjm,
            ],
            'screening' => [
                'workAuthorisation' => $this->workAuthorisation,
            ],
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
