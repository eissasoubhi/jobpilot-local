<?php

declare(strict_types=1);

namespace App\Messaging\Application;

final class GmailMessageClassifier
{
    /**
     * @return array{category: string, reason: string, actionRequired: bool}
     */
    public function classify(string $subject, string $sender, string $body): array
    {
        $text = $this->normalize($subject.' '.$sender.' '.$body);

        return match (true) {
            $this->containsAny($text, [
                'entretien', 'interview', 'technical interview', 'entretien technique',
                'meeting invitation', 'rendez vous', 'rendez-vous', 'calendly',
            ]) => $this->result('INTERVIEW_REQUEST', 'Une invitation à un entretien ou à un rendez-vous a été détectée.', true),

            $this->containsAny($text, [
                'malheureusement', 'nous ne donnerons pas suite', 'ne pas donner suite',
                'candidature non retenue', 'profil non retenu', 'not retained',
                'unsuccessful application', 'we will not be moving forward', 'rejet', 'refus',
            ]) => $this->result('REJECTION', 'Le message contient une réponse négative à une candidature.', false),

            $this->containsAny($text, [
                'besoin de renseignements', 'informations complémentaires', 'information complémentaire',
                'pouvez vous nous transmettre', 'pouvez-vous nous transmettre', 'merci de nous envoyer',
                'could you provide', 'additional information', 'please provide', 'availability',
                'disponibilite', 'disponibilité', 'pretentions salariales', 'prétentions salariales',
            ]) => $this->result('INFORMATION_REQUEST', 'Le recruteur demande des informations ou une action complémentaire.', true),

            $this->containsAny($text, [
                'candidature reçue', 'candidature a bien été reçue', 'application received',
                'thanks for applying', 'thank you for applying', 'confirmation de candidature',
                'votre candidature a été transmise',
            ]) => $this->result('APPLICATION_CONFIRMATION', 'Le message confirme la réception ou la transmission d’une candidature.', false),

            $this->containsAny($text, [
                'nouvelle mission', 'opportunite', 'opportunité', 'nous recherchons',
                'je recherche', 'votre profil a retenu mon attention', 'votre profil nous intéresse',
                'tjm', 'freelance', 'consultant', 'mission chez', 'mission pour',
                'new opportunity', 'job opportunity', 'recruiter', 'talent acquisition',
            ]) => $this->result('RECRUITER_OPPORTUNITY', 'Une proposition de poste ou de mission envoyée par un recruteur a été détectée.', true),

            $this->containsAny($text, [
                'alerte emploi', 'alerte job', 'job alert', 'new jobs', 'new job',
                'offres pour vous', 'offres correspondant', 'emplois correspondant',
                'nouvelles offres', 'recommended jobs', 'jobs for you', 'offres d emploi',
            ]) => $this->result('JOB_ALERT', 'Le message ressemble à une alerte contenant une ou plusieurs offres.', false),

            $this->containsAny($text, [
                'suite a votre candidature', 'suite à votre candidature', 'regarding your application',
                'about your application', 'retour sur votre candidature', 'votre candidature',
            ]) => $this->result('APPLICATION_REPLY', 'Le message semble être une réponse liée à une candidature existante.', true),

            default => $this->result('UNKNOWN', 'Aucune règle métier suffisamment fiable ne correspond au message.', false),
        };
    }

    /** @return array{category: string, reason: string, actionRequired: bool} */
    private function result(string $category, string $reason, bool $actionRequired): array
    {
        return [
            'category' => $category,
            'reason' => $reason,
            'actionRequired' => $actionRequired,
        ];
    }

    /** @param list<string> $needles */
    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $this->normalize($needle))) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a',
            'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            '’' => "'",
        ]);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
