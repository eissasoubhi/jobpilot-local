<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;

final class ApplicationMotivationRegenerator
{
    public function __construct(private GroundedCoverLetterBuilder $coverLetterBuilder) {}

    public function message(JobOffer $job, CandidateProfile $profile, int $maxCharacters = 400): string
    {
        $profileData = $profile->toArray();
        $name = $this->inline((string) ($profileData['fullName'] ?? ''), 70);
        $years = max(0, (int) ($profileData['yearsOfExperience'] ?? 0));
        $availability = trim((string) ($profileData['availability'] ?? ''));
        $role = $this->inline($job->getTitle(), 95);
        $company = $this->inline($job->getCompany(), 70);

        $candidates = $job->getLanguage() === 'en'
            ? $this->englishMessageCandidates($role, $company, $name, $years, $availability)
            : $this->frenchMessageCandidates($role, $company, $name, $years, $availability);

        return $this->fit($candidates, $maxCharacters);
    }

    /**
     * @param list<string> $profileSkills
     */
    public function coverLetter(
        JobOffer $job,
        CandidateProfile $profile,
        array $profileSkills,
        int $maxCharacters = 1_500,
    ): string {
        $full = $this->coverLetterBuilder->build($job, $profile, $profileSkills);
        $profileData = $profile->toArray();
        $name = $this->inline((string) ($profileData['fullName'] ?? ''), 70);
        $years = max(0, (int) ($profileData['yearsOfExperience'] ?? 0));
        $availability = trim((string) ($profileData['availability'] ?? ''));
        $role = $this->inline($job->getTitle(), 110);
        $company = $this->inline($job->getCompany(), 80);
        $skills = $this->matchingSkills($job, $profileSkills);

        $candidates = $job->getLanguage() === 'en'
            ? [$full, ...$this->englishCoverLetterCandidates($role, $company, $name, $years, $availability, $skills)]
            : [$full, ...$this->frenchCoverLetterCandidates($role, $company, $name, $years, $availability, $skills)];

        return $this->fit($candidates, $maxCharacters);
    }

    /** @return list<string> */
    private function frenchMessageCandidates(string $role, string $company, string $name, int $years, string $availability): array
    {
        $target = $role !== '' ? ' pour le poste de '.$role : '';
        $companySuffix = $company !== '' ? ' chez '.$company : '';
        $experience = $years > 0 ? " Avec {$years} ans d’expérience en développement web, mon parcours correspond bien aux enjeux de la mission." : '';
        $available = $this->frenchAvailability($availability);
        $signature = $name !== '' ? ' '.$name : '';

        return [
            "Bonjour, je vous propose ma candidature{$target}{$companySuffix}.{$experience} {$available} et serais ravi d’échanger avec vous. Bien cordialement,{$signature}",
            "Bonjour, je suis intéressé par le poste de {$role}{$companySuffix}.{$experience} {$available}. Je serais ravi d’échanger avec vous. Bien cordialement,{$signature}",
            "Bonjour, votre offre de {$role} correspond à mon parcours. {$available} et serais ravi d’échanger avec vous. Bien cordialement,{$signature}",
            "Bonjour, je suis intéressé par votre offre de {$role}. {$available}. Bien cordialement,{$signature}",
        ];
    }

    /** @return list<string> */
    private function englishMessageCandidates(string $role, string $company, string $name, int $years, string $availability): array
    {
        $target = $role !== '' ? ' for the '.$role.' role' : '';
        $companySuffix = $company !== '' ? ' at '.$company : '';
        $experience = $years > 0 ? " With {$years} years of web development experience, my background is well aligned with the role." : '';
        $available = $this->englishAvailability($availability);
        $signature = $name !== '' ? ' '.$name : '';

        return [
            "Hello, I would like to apply{$target}{$companySuffix}.{$experience} {$available}, and I would be glad to discuss the opportunity with you. Best regards,{$signature}",
            "Hello, I am interested in the {$role} role{$companySuffix}.{$experience} {$available}. I would be glad to discuss it with you. Best regards,{$signature}",
            "Hello, your {$role} opening matches my background. {$available}, and I would be glad to discuss it with you. Best regards,{$signature}",
            "Hello, I am interested in your {$role} opening. {$available}. Best regards,{$signature}",
        ];
    }

    /**
     * @param list<string> $skills
     * @return list<string>
     */
    private function frenchCoverLetterCandidates(
        string $role,
        string $company,
        string $name,
        int $years,
        string $availability,
        array $skills,
    ): array {
        $companySuffix = $company !== '' ? ' chez '.$company : '';
        $experience = $years > 0 ? "Avec {$years} ans d’expérience en développement web" : 'Avec mon expérience en développement web';
        $skillsSentence = $skills !== []
            ? ' Votre environnement met en avant '.$this->formatList($skills, 'et').', des technologies directement liées à mon parcours.'
            : '';
        $availabilitySentence = ucfirst($this->frenchAvailability($availability)).'.';
        $signature = $name !== '' ? "\n{$name}" : '';

        return [
            "Madame, Monsieur,\n\nJe vous adresse ma candidature pour le poste de {$role}{$companySuffix}. {$experience}, je souhaite mettre mon expérience au service de cette mission.{$skillsSentence}\n\nLes responsabilités décrites correspondent à la manière dont j’aborde mes projets : comprendre le besoin, contribuer aux choix techniques et livrer des solutions fiables et maintenables. Je serais heureux de mettre cette expérience au service de votre équipe.\n\n{$availabilitySentence} Je serais ravi d’échanger avec vous afin de détailler mon parcours et ma motivation.\n\nBien cordialement,{$signature}",
            "Madame, Monsieur,\n\nJe souhaite vous proposer ma candidature pour le poste de {$role}{$companySuffix}. {$experience}, mon parcours correspond aux enjeux techniques présentés dans votre offre.{$skillsSentence}\n\n{$availabilitySentence} Je serais ravi d’échanger avec vous sur vos besoins et sur la contribution que je pourrais apporter à l’équipe.\n\nBien cordialement,{$signature}",
            "Madame, Monsieur,\n\nLe poste de {$role}{$companySuffix} correspond à mon parcours. {$experience}, je souhaite mettre mon expérience et mon attention à la qualité du code au service de votre équipe.\n\n{$availabilitySentence} Je serais ravi d’échanger avec vous.\n\nBien cordialement,{$signature}",
        ];
    }

    /**
     * @param list<string> $skills
     * @return list<string>
     */
    private function englishCoverLetterCandidates(
        string $role,
        string $company,
        string $name,
        int $years,
        string $availability,
        array $skills,
    ): array {
        $companySuffix = $company !== '' ? ' at '.$company : '';
        $experience = $years > 0 ? "With {$years} years of web development experience" : 'With my web development experience';
        $skillsSentence = $skills !== []
            ? ' Your environment highlights '.$this->formatList($skills, 'and').', technologies directly related to my background.'
            : '';
        $availabilitySentence = ucfirst($this->englishAvailability($availability)).'.';
        $signature = $name !== '' ? "\n{$name}" : '';

        return [
            "Dear Hiring Team,\n\nI am applying for the {$role} position{$companySuffix}. {$experience}, I would like to bring my background to this opportunity.{$skillsSentence}\n\nThe responsibilities described match the way I approach projects: understanding the need, contributing to implementation decisions, and delivering reliable, maintainable solutions. I would be glad to bring this experience to your team.\n\n{$availabilitySentence} I would welcome the opportunity to discuss my background and motivation in more detail.\n\nBest regards,{$signature}",
            "Dear Hiring Team,\n\nI would like to apply for the {$role} position{$companySuffix}. {$experience}, my background is closely aligned with the technical responsibilities in your offer.{$skillsSentence}\n\n{$availabilitySentence} I would be glad to discuss your needs and how I could contribute to the team.\n\nBest regards,{$signature}",
            "Dear Hiring Team,\n\nThe {$role} position{$companySuffix} is closely aligned with my background. {$experience}, I would like to bring my experience and focus on code quality to your team.\n\n{$availabilitySentence} I would be glad to discuss the opportunity with you.\n\nBest regards,{$signature}",
        ];
    }

    /**
     * @param list<string> $candidates
     */
    private function fit(array $candidates, int $maxCharacters): string
    {
        $maxCharacters = max(1, $maxCharacters);
        foreach ($candidates as $candidate) {
            $candidate = trim(preg_replace('/[ \t]+/u', ' ', $candidate) ?? $candidate);
            if (mb_strlen($candidate) <= $maxCharacters) {
                return $candidate;
            }
        }

        $shortest = trim((string) end($candidates));
        if (mb_strlen($shortest) <= $maxCharacters) {
            return $shortest;
        }
        if ($maxCharacters === 1) {
            return '…';
        }

        $slice = rtrim(mb_substr($shortest, 0, $maxCharacters - 1));
        $lastSpace = mb_strrpos($slice, ' ');
        if ($lastSpace !== false && $lastSpace >= (int) floor($maxCharacters * 0.65)) {
            $slice = rtrim(mb_substr($slice, 0, $lastSpace));
        }

        return rtrim($slice, " ,.;:-\n\r\t").'…';
    }

    /**
     * @param list<string> $profileSkills
     * @return list<string>
     */
    private function matchingSkills(JobOffer $job, array $profileSkills): array
    {
        $offer = mb_strtolower(strip_tags($job->getTitle().' '.$job->getDescription()));
        $matches = [];
        foreach ($profileSkills as $skill) {
            $skill = trim((string) $skill);
            if ($skill === '' || !str_contains($offer, mb_strtolower($skill))) {
                continue;
            }
            $matches[] = $this->inline($skill, 40);
            if (count($matches) === 4) {
                break;
            }
        }

        return $matches;
    }

    /** @param list<string> $values */
    private function formatList(array $values, string $conjunction): string
    {
        if (count($values) <= 1) {
            return $values[0] ?? '';
        }
        $last = array_pop($values);

        return implode(', ', $values).' '.$conjunction.' '.$last;
    }

    private function frenchAvailability(string $availability): string
    {
        if ($availability === '' || str_contains(mb_strtolower($availability), 'imm')) {
            return 'je suis disponible immédiatement';
        }

        return 'ma disponibilité est '.$this->inline($availability, 60);
    }

    private function englishAvailability(string $availability): string
    {
        if ($availability === '' || str_contains(mb_strtolower($availability), 'imm')) {
            return 'I am available immediately';
        }

        return 'my availability is '.$this->inline($availability, 60);
    }

    private function inline(string $value, int $maxCharacters): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
        if (mb_strlen($value) <= $maxCharacters) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, max(1, $maxCharacters - 1))).'…';
    }
}
