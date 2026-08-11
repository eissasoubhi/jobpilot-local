<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;

final class GroundedCoverLetterBuilder
{
    /**
     * @param list<string> $profileSkills
     */
    public function build(JobOffer $job, CandidateProfile $profile, array $profileSkills = []): string
    {
        $profileData = $profile->toArray();
        $name = trim((string) ($profileData['fullName'] ?? ''));
        $years = max(0, (int) ($profileData['yearsOfExperience'] ?? 0));
        $availability = trim((string) ($profileData['availability'] ?? ''));
        $matchingSkills = $this->matchingSkills($job, $profileSkills);

        return $job->getLanguage() === 'en'
            ? $this->english($job, $name, $years, $availability, $matchingSkills)
            : $this->french($job, $name, $years, $availability, $matchingSkills);
    }

    /**
     * @param list<string> $matchingSkills
     */
    private function french(
        JobOffer $job,
        string $name,
        int $years,
        string $availability,
        array $matchingSkills,
    ): string {
        $company = trim($job->getCompany());
        $role = trim($job->getTitle()) !== '' ? trim($job->getTitle()) : 'proposé';
        $companySuffix = $company !== '' ? ' chez '.$company : '';
        $experience = $years > 0
            ? "Avec {$years} ans d’expérience professionnelle, je souhaite mettre mon parcours au service d’une mission dont les responsabilités s’inscrivent dans la continuité de mon expérience du développement web."
            : 'Je souhaite mettre mon parcours au service d’une mission dont les responsabilités s’inscrivent dans la continuité de mon expérience du développement web.';
        $technicalParagraph = $this->frenchTechnicalParagraph($matchingSkills);
        $availabilitySentence = $this->frenchAvailability($availability);
        $signature = $name !== '' ? "\n{$name}" : '';

        return "Madame, Monsieur,\n\n"
            ."Je vous adresse ma candidature pour le poste de {$role}{$companySuffix}. {$experience}\n\n"
            .$technicalParagraph."\n\n"
            .'Je recherche également un environnement où je peux participer aux choix de réalisation, partager mon expérience et continuer à progresser au contact des enjeux du produit. Cette combinaison entre contribution technique, compréhension du besoin et travail d’équipe correspond à la manière dont j’aborde mes missions.'
            ."\n\n{$availabilitySentence} Je serais ravi d’échanger avec vous afin de détailler mon parcours et de voir comment je peux contribuer à vos besoins."
            ."\n\nBien cordialement,{$signature}";
    }

    /**
     * @param list<string> $matchingSkills
     */
    private function english(
        JobOffer $job,
        string $name,
        int $years,
        string $availability,
        array $matchingSkills,
    ): string {
        $company = trim($job->getCompany());
        $role = trim($job->getTitle()) !== '' ? trim($job->getTitle()) : 'this role';
        $companySuffix = $company !== '' ? ' at '.$company : '';
        $experience = $years > 0
            ? "With {$years} years of professional experience, I am interested in bringing my background to a role whose responsibilities are closely aligned with web application development and product delivery."
            : 'I am interested in bringing my professional background to a role whose responsibilities are closely aligned with web application development and product delivery.';
        $technicalParagraph = $this->englishTechnicalParagraph($matchingSkills);
        $availabilitySentence = $this->englishAvailability($availability);
        $signature = $name !== '' ? "\n{$name}" : '';

        return "Dear Hiring Team,\n\n"
            ."I am applying for the {$role} position{$companySuffix}. {$experience}\n\n"
            .$technicalParagraph."\n\n"
            .'Beyond the technical fit, I am looking for a role where I can contribute my experience, take part in implementation decisions, and stay focused on code quality, readability, and product evolution. This combination of technical contribution, understanding the need, and teamwork reflects how I approach my work.'
            ."\n\n{$availabilitySentence} I would be glad to discuss my background in more detail and explore how I could contribute to your needs."
            ."\n\nBest regards,{$signature}";
    }

    /**
     * @param list<string> $skills
     */
    private function frenchTechnicalParagraph(array $skills): string
    {
        if ($skills === []) {
            return 'Les responsabilités décrites dans votre offre retiennent particulièrement mon attention. Je souhaite mobiliser mon expérience pour comprendre rapidement votre contexte, contribuer aux développements attendus et collaborer efficacement avec l’équipe. Mon objectif est d’apporter une contribution concrète tout en restant attentif à la qualité des réalisations, à la lisibilité du code et à la maintenabilité des solutions mises en place.';
        }

        $formatted = $this->formatList($skills, 'et');

        return "Votre offre met en avant {$formatted}. Ces technologies font partie de mon environnement technique et leur présence dans votre contexte renforce mon intérêt pour le poste. Je peux ainsi m’appuyer sur un socle directement pertinent pour comprendre rapidement la mission, contribuer aux développements attendus et travailler avec l’équipe sur des solutions fiables, lisibles et maintenables.";
    }

    /**
     * @param list<string> $skills
     */
    private function englishTechnicalParagraph(array $skills): string
    {
        if ($skills === []) {
            return 'The responsibilities described in your offer are particularly relevant to me. I want to use my experience to understand your context quickly, contribute to the expected developments, and collaborate effectively with the team. My goal is to make a concrete contribution while remaining attentive to implementation quality, code readability, and the maintainability of the solutions delivered.';
        }

        $formatted = $this->formatList($skills, 'and');

        return "Your offer highlights {$formatted}. These technologies are part of my technical background, and seeing them in your environment makes the opportunity particularly relevant to me. I can therefore rely on a directly relevant foundation to understand the context quickly, contribute to the expected developments, and work with the team on solutions that remain reliable, clear, and maintainable over time.";
    }

    /**
     * @param list<string> $profileSkills
     * @return list<string>
     */
    private function matchingSkills(JobOffer $job, array $profileSkills): array
    {
        $offerText = $this->normalizeForSearch($job->getTitle().' '.$job->getDescription());
        if ($offerText === '') {
            return [];
        }

        $matches = [];
        $seen = [];
        foreach ($profileSkills as $skill) {
            $skill = trim((string) $skill);
            if ($skill === '') {
                continue;
            }

            $normalizedSkill = $this->normalizeForSearch($skill);
            if ($normalizedSkill === '' || !str_contains(' '.$offerText.' ', ' '.$normalizedSkill.' ')) {
                continue;
            }

            $key = mb_strtolower($skill);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $matches[] = $skill;
            if (count($matches) === 4) {
                break;
            }
        }

        return $matches;
    }

    private function normalizeForSearch(string $value): string
    {
        $value = mb_strtolower(strip_tags($value));
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /**
     * @param list<string> $values
     */
    private function formatList(array $values, string $conjunction): string
    {
        if (count($values) === 1) {
            return $values[0];
        }

        $last = array_pop($values);

        return implode(', ', $values).' '.$conjunction.' '.$last;
    }

    private function frenchAvailability(string $availability): string
    {
        if ($availability === '') {
            return 'Je suis disponible pour convenir d’une date de démarrage.';
        }

        if (str_contains(mb_strtolower($availability), 'imm')) {
            return 'Je suis disponible immédiatement.';
        }

        return 'Ma disponibilité est : '.$availability.'.';
    }

    private function englishAvailability(string $availability): string
    {
        if ($availability === '') {
            return 'I am available to agree on a suitable start date.';
        }

        if (str_contains(mb_strtolower($availability), 'imm')) {
            return 'I am available immediately.';
        }

        return 'My availability is: '.$availability.'.';
    }
}
