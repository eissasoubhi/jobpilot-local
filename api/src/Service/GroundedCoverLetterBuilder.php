<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;

final class GroundedCoverLetterBuilder
{
    public function build(JobOffer $job, CandidateProfile $profile): string
    {
        $profileData = $profile->toArray();
        $name = trim((string) ($profileData['fullName'] ?? ''));
        $years = max(0, (int) ($profileData['yearsOfExperience'] ?? 0));
        $availability = trim((string) ($profileData['availability'] ?? ''));

        return $job->getLanguage() === 'en'
            ? $this->english($job, $name, $years, $availability)
            : $this->french($job, $name, $years, $availability);
    }

    private function french(JobOffer $job, string $name, int $years, string $availability): string
    {
        $company = trim($job->getCompany());
        $role = trim($job->getTitle()) !== '' ? $job->getTitle() : 'proposé';
        $companySuffix = $company !== '' ? ' chez '.$company : '';
        $experience = $years > 0
            ? "Avec {$years} ans d’expérience professionnelle, je souhaite mettre mon parcours au service de cette opportunité."
            : 'Je souhaite mettre mon parcours professionnel au service de cette opportunité.';
        $availabilitySentence = $this->frenchAvailability($availability);
        $signature = $name !== '' ? "\n{$name}" : '';

        return "Madame, Monsieur,\n\nJe vous adresse ma candidature pour le poste de {$role}{$companySuffix}. {$experience} Après avoir étudié les responsabilités décrites dans votre offre, je suis particulièrement intéressé par cette opportunité et par la possibilité de contribuer à vos projets.\n\n{$availabilitySentence} Je serais ravi d’échanger avec vous afin de détailler mon parcours et d’évaluer son adéquation avec vos besoins.\n\nBien cordialement,{$signature}";
    }

    private function english(JobOffer $job, string $name, int $years, string $availability): string
    {
        $company = trim($job->getCompany());
        $role = trim($job->getTitle()) !== '' ? $job->getTitle() : 'this role';
        $companySuffix = $company !== '' ? ' at '.$company : '';
        $experience = $years > 0
            ? "With {$years} years of professional experience, I would welcome the opportunity to bring my background to this role."
            : 'I would welcome the opportunity to bring my professional background to this role.';
        $availabilitySentence = $this->englishAvailability($availability);
        $signature = $name !== '' ? "\n{$name}" : '';

        return "Dear Hiring Team,\n\nI am applying for the {$role} position{$companySuffix}. {$experience} Having reviewed the responsibilities described in the offer, I am particularly interested in the opportunity and in contributing to your projects.\n\n{$availabilitySentence} I would be glad to discuss my background in more detail and assess how it fits your needs.\n\nBest regards,{$signature}";
    }

    private function frenchAvailability(string $availability): string
    {
        if ($availability === '') {
            return 'Je suis disponible pour convenir d’une date de démarrage.';
        }

        if (str_contains(mb_strtolower($availability), 'imm')) {
            return 'Je suis disponible immédiatement.';
        }

        return 'Ma disponibilité enregistrée est : '.$availability.'.';
    }

    private function englishAvailability(string $availability): string
    {
        if ($availability === '') {
            return 'I am available to agree on a suitable start date.';
        }

        if (str_contains(mb_strtolower($availability), 'imm')) {
            return 'I am available immediately.';
        }

        return 'My recorded availability is: '.$availability.'.';
    }
}
