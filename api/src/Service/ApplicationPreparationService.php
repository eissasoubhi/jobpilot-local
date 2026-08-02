<?php

namespace App\Service;

use App\Entity\Application;
use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use Doctrine\ORM\EntityManagerInterface;

final class ApplicationPreparationService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function prepare(JobOffer $job, CandidateProfile $profile): Application
    {
        $existing = $this->em->getRepository(Application::class)->findOneBy(['jobOffer' => $job]);
        $application = $existing ?? new Application($job);
        $profileData = $profile->toArray();
        $name = $profileData['fullName'];
        $years = $profileData['yearsOfExperience'];
        $available = $profileData['availability'];

        if ($job->getLanguage() === 'en') {
            $message = "Hello,\n\nI am interested in the {$job->getTitle()} position at {$job->getCompany()}. I have {$years} years of web development experience and strong experience with PHP/Symfony and modern frontend frameworks. I am {$this->englishAvailability($available)}.\n\nBest regards,\n{$name}";
            $cover = "Dear Hiring Team,\n\nMy background combines senior PHP/Symfony development with React, Next.js, Vue.js and Nuxt.js. The responsibilities described for {$job->getTitle()} are closely aligned with my experience in backend APIs, full-stack delivery, software quality and production environments.\n\nI would be pleased to discuss the role and the technical context in more detail.\n\nBest regards,\n{$name}";
        } else {
            $message = "Bonjour,\n\nJe suis intéressé par le poste de {$job->getTitle()} chez {$job->getCompany()}. J'ai {$years} ans d'expérience en développement web, avec une forte expertise en PHP/Symfony et sur les frameworks frontend modernes. Je suis disponible {$this->frenchAvailability($available)}.\n\nBien cordialement,\n{$name}";
            $cover = "Madame, Monsieur,\n\nMon parcours combine une expertise senior en PHP/Symfony avec React, Next.js, Vue.js et Nuxt.js. Les responsabilités décrites pour le poste de {$job->getTitle()} correspondent à mon expérience des API, du développement full-stack, de la qualité logicielle et des environnements de production.\n\nJe serais ravi d'échanger sur le poste et son contexte technique.\n\nBien cordialement,\n{$name}";
        }

        $compensation = null;
        if ($job->getProposedTjm() !== null) {
            $compensation = $job->getProposedTjm().' € HT/jour';
        } elseif ($job->getProposedSalary() !== null) {
            $compensation = number_format($job->getProposedSalary(), 0, ',', ' ').' € brut annuel (rémunération globale)';
        }
        $application->prepare($job->getRecommendedCv(), $message, $cover, $compensation);
        $job->markPrepared();
        $this->em->persist($application);
        $this->em->flush();
        return $application;
    }

    private function englishAvailability(string $value): string { return str_contains(mb_strtolower($value), 'imm') ? 'available immediately' : $value; }
    private function frenchAvailability(string $value): string { return str_contains(mb_strtolower($value), 'imm') ? 'immédiatement' : $value; }
}
