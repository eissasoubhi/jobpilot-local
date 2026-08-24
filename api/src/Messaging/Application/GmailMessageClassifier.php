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

            $this->isPlatformAlert($subject, $sender, $body) => $this->result(
                'JOB_ALERT',
                'Le message provient d’une plateforme emploi et ressemble à une alerte ou un digest, pas à une proposition directe de recruteur.',
                false,
            ),

            $this->isRecruiterInformationalAcknowledgement($text) => $this->result(
                'RECRUITER_INFORMATIONAL',
                'Le recruteur confirme la prise en compte et indique qu’il reviendra vers le candidat sans demander d’action immédiate.',
                false,
            ),

            $this->containsAny($text, [
                'nouvelle mission', 'opportunite', 'opportunité', 'nous recherchons',
                'je recherche', 'votre profil a retenu mon attention', 'votre profil nous intéresse',
                'tjm', 'freelance', 'consultant', 'mission chez', 'mission pour',
                'new opportunity', 'job opportunity', 'recruiter', 'talent acquisition',
            ]) => $this->result('RECRUITER_OPPORTUNITY', 'Une proposition de poste ou de mission envoyée par un recruteur a été détectée.', true),

            $this->containsAny($text, [
                'suite a votre candidature', 'suite à votre candidature', 'regarding your application',
                'about your application', 'retour sur votre candidature', 'votre candidature',
            ]) => $this->result('APPLICATION_REPLY', 'Le message semble être une réponse liée à une candidature existante.', true),

            default => $this->result('UNKNOWN', 'Aucune règle métier suffisamment fiable ne correspond au message.', false),
        };
    }

    private function isRecruiterInformationalAcknowledgement(string $text): bool
    {
        $acknowledgement = $this->containsAny($text, [
            'merci pour votre retour', 'merci pour ces informations', 'merci pour les informations',
            'merci pour votre candidature', 'bien reçu', 'well received', 'thanks for the information',
            'thank you for the information', 'thanks for your reply', 'thank you for your reply',
        ]);
        $handoff = $this->containsAny($text, [
            'je transmets votre cv', 'je transmets votre profil', 'je partage votre cv',
            'je partage votre profil', 'je transfere votre cv', 'je transfère votre cv',
            'i have forwarded your cv', 'i forwarded your cv', 'i have shared your profile',
        ]);
        $followUpLater = $this->containsAny($text, [
            'je reviens vers vous', 'nous revenons vers vous', 'je vous tiens au courant',
            'nous vous tenons au courant', 'i will get back to you', "i'll get back to you",
            'we will get back to you', "we'll get back to you", 'i will keep you posted',
        ]);

        return ($acknowledgement || $handoff) && $followUpLater;
    }

    private function isPlatformAlert(string $subject, string $sender, string $body): bool
    {
        $subjectText = $this->normalize($subject);
        $senderText = $this->normalize($sender);
        $bodyText = $this->normalize($body);
        $combined = trim($subjectText.' '.$bodyText);

        if ($this->containsAny($combined, [
            'alerte emploi', 'alerte job', 'job alert', 'new jobs', 'new job',
            'offres pour vous', 'offres correspondant', 'emplois correspondant',
            'nouvelles offres', 'recommended jobs', 'jobs for you', 'offres d emploi',
            'selection d offres', 'sélection d offres', 'recommandations d offres',
        ])) {
            return true;
        }

        if (!$this->containsAny($senderText, [
            'jobijoba', 'hellowork', 'apec', 'indeed', 'linkedin',
            'welcome to the jungle', 'welcometothejungle', 'free-work', 'free work',
            'lesjeudis', 'talent.com', 'jooble',
        ])) {
            return false;
        }

        return $this->containsAny($combined, [
            'vous proposent des offres', 'vous propose des offres', 'des offres pour vous',
            'offres selectionnees', 'offres sélectionnées', 'offres recommandees', 'offres recommandées',
            'emplois pour vous', 'jobs selected for you', 'jobs recommended for you',
            'correspondent a votre recherche', 'correspondent à votre recherche',
            'correspondant a votre recherche', 'correspondant à votre recherche',
            'offres', 'jobs',
        ]);
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
