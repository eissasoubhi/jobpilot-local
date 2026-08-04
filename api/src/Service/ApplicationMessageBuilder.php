<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;

final class ApplicationMessageBuilder
{
    /**
     * @return array{message: string, coverLetter: string}
     */
    public function build(JobOffer $job, CandidateProfile $profile): array
    {
        $profileData = $profile->toArray();
        $name = trim((string) $profileData['fullName']);
        $years = max(0, (int) $profileData['yearsOfExperience']);
        $availability = trim((string) $profileData['availability']);
        $skills = $this->relevantSkills($job);
        $focus = $this->focus($job);

        if ($job->getLanguage() === 'en') {
            $company = $job->getCompany() !== '' ? ' at '.$job->getCompany() : '';
            $message = "Hello,\n\nThe {$job->getTitle()} role{$company} is a strong match for my background. I am a senior developer with {$years} years of web development experience and strong expertise in {$this->join($skills, 'en')}. My work covers {$focus['en']}, with consistent attention to code quality and production reliability.\n\nI am {$this->englishAvailability($availability)} and would be glad to discuss your needs. My CV is attached.\n\nBest regards,\n{$name}";
            $coverLetter = "Dear Hiring Team,\n\nWith {$years} years of web development experience, I have built and maintained production applications using {$this->join($skills, 'en')}. My experience in {$focus['en']} is closely aligned with the responsibilities described for the {$job->getTitle()} role.\n\nI would be pleased to discuss how I could contribute to your team and technical objectives.\n\nBest regards,\n{$name}";

            return ['message' => $message, 'coverLetter' => $coverLetter];
        }

        $company = $job->getCompany() !== '' ? ' chez '.$job->getCompany() : '';
        $message = "Bonjour,\n\nLe poste de {$job->getTitle()}{$company} correspond directement à mon parcours. Développeur senior avec {$years} ans d’expérience en développement web, j’ai une solide expérience en {$this->join($skills, 'fr')}. Mon travail couvre {$focus['fr']}, avec une attention constante à la qualité du code et à la fiabilité en production.\n\nJe suis disponible {$this->frenchAvailability($availability)} et serais ravi d’échanger sur vos besoins. Vous trouverez mon CV en pièce jointe.\n\nBien cordialement,\n{$name}";
        $coverLetter = "Madame, Monsieur,\n\nAvec {$years} ans d’expérience en développement web, j’ai conçu et maintenu des applications en production avec {$this->join($skills, 'fr')}. Mon expérience en {$focus['fr']} correspond directement aux responsabilités décrites pour le poste de {$job->getTitle()}.\n\nJe serais ravi d’échanger sur la manière dont je pourrais contribuer à votre équipe et à vos objectifs techniques.\n\nBien cordialement,\n{$name}";

        return ['message' => $message, 'coverLetter' => $coverLetter];
    }

    /**
     * @return list<string>
     */
    private function relevantSkills(JobOffer $job): array
    {
        $text = mb_strtolower($job->getTitle().' '.$job->getDescription());
        $hexagonalLabel = $job->getLanguage() === 'en'
            ? 'hexagonal architecture'
            : 'architecture hexagonale';
        $catalogue = [
            'API Platform' => '/api[ -]?platform/i',
            'Symfony' => '/\bsymfony\b/i',
            'PHP' => '/\bphp\b/i',
            'Doctrine' => '/\bdoctrine\b/i',
            'React' => '/\breact(?:\.js)?\b/i',
            'Next.js' => '/\bnext(?:\.js|js)?\b/i',
            'Vue.js' => '/\bvue(?:\.js|js)?\b/i',
            'Nuxt.js' => '/\bnuxt(?:\.js|js)?\b/i',
            'TypeScript' => '/\btypescript\b/i',
            'JavaScript' => '/\bjavascript\b/i',
            'Twig' => '/\btwig\b/i',
            'RabbitMQ' => '/\brabbitmq\b/i',
            'Redis' => '/\bredis\b/i',
            'Elasticsearch' => '/\belasticsearch\b/i',
            'PostgreSQL' => '/\bpostgres(?:ql)?\b/i',
            'MySQL' => '/\bmysql\b/i',
            'Docker' => '/\bdocker\b/i',
            'Kubernetes' => '/\b(?:kubernetes|k8s)\b/i',
            'GitLab CI/CD' => '/gitlab\s+ci|gitlab\s+ci\/cd/i',
            'Jenkins' => '/\bjenkins\b/i',
            'AWS' => '/\baws\b/i',
            'GCP' => '/\bgcp\b|google cloud/i',
            'DDD' => '/\bddd\b|domain[ -]driven design/i',
            $hexagonalLabel => '/architecture hexagonale|hexagonal architecture/i',
        ];

        $skills = [];
        foreach ($catalogue as $label => $pattern) {
            if (preg_match($pattern, $text) !== 1) {
                continue;
            }

            $skills[] = $label;
            if (count($skills) === 4) {
                break;
            }
        }

        if ($skills !== []) {
            return $skills;
        }

        if (preg_match('/front[ -]?end|frontend|interface|ui\b/i', $text) === 1) {
            return ['React', 'Next.js', 'TypeScript'];
        }

        if (preg_match('/full[ -]?stack|fullstack/i', $text) === 1) {
            return ['PHP/Symfony', 'React/Next.js', 'TypeScript'];
        }

        return $job->getLanguage() === 'en'
            ? ['PHP', 'Symfony', 'API development']
            : ['PHP', 'Symfony', 'développement d’API'];
    }

    /**
     * @return array{fr: string, en: string}
     */
    private function focus(JobOffer $job): array
    {
        $text = mb_strtolower($job->getTitle().' '.$job->getDescription());

        if (preg_match('/front[ -]?end|frontend|interface|ui\b|react|vue|next(?:\.js|js)?|nuxt/i', $text) === 1
            && preg_match('/back[ -]?end|backend|api|php|symfony/i', $text) !== 1) {
            return [
                'fr' => 'la réalisation d’interfaces performantes, l’accessibilité et l’expérience utilisateur',
                'en' => 'high-performance user interfaces, accessibility and user experience',
            ];
        }

        if (preg_match('/full[ -]?stack|fullstack/i', $text) === 1
            || (preg_match('/front[ -]?end|frontend|react|vue|next(?:\.js|js)?|nuxt/i', $text) === 1
                && preg_match('/back[ -]?end|backend|api|php|symfony/i', $text) === 1)) {
            return [
                'fr' => 'la conception d’API, les interfaces modernes et la mise en production',
                'en' => 'API design, modern interfaces and production delivery',
            ];
        }

        return [
            'fr' => 'la conception d’API, l’architecture applicative et la performance',
            'en' => 'API design, application architecture and performance',
        ];
    }

    /**
     * @param list<string> $items
     */
    private function join(array $items, string $language): string
    {
        $items = array_values(array_filter(array_map('trim', $items)));
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);
        $separator = $language === 'en' ? ' and ' : ' et ';

        return implode(', ', $items).$separator.$last;
    }

    private function englishAvailability(string $value): string
    {
        if ($value === '') {
            return 'available to agree on a start date';
        }

        return str_contains(mb_strtolower($value), 'imm') ? 'available immediately' : $value;
    }

    private function frenchAvailability(string $value): string
    {
        if ($value === '') {
            return 'pour convenir d’une date de démarrage';
        }

        return str_contains(mb_strtolower($value), 'imm') ? 'immédiatement' : $value;
    }
}
