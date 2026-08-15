<?php

declare(strict_types=1);

namespace App\Messaging\Application;

use App\Entity\InboxMessage;

final class InboxMessageUrgencyEvaluator
{
    /**
     * @return array{
     *   level: 'URGENT'|'PRIORITY'|'NORMAL',
     *   label: string,
     *   actionRequired: bool,
     *   reasons: list<string>,
     *   recommendedAction: string|null,
     *   ageHours: float
     * }
     */
    public function evaluate(InboxMessage $message, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $ageHours = max(0.0, ($now->getTimestamp() - $message->getReceivedAt()->getTimestamp()) / 3600);
        $actionRequired = $message->isActionRequired() && !$message->isProcessed();

        if ($message->isProcessed()) {
            return $this->result('NORMAL', false, [], null, $ageHours);
        }

        [$score, $reasons] = match ($message->getCategory()) {
            'INTERVIEW_REQUEST' => [80, ['Entretien ou rendez-vous à organiser.']],
            'INFORMATION_REQUEST' => [75, ['Le recruteur attend des informations ou une réponse.']],
            'RECRUITER_OPPORTUNITY' => [50, ['Proposition directe d’un recruteur.']],
            'APPLICATION_REPLY' => [45, ['Réponse reçue sur une candidature.']],
            default => [0, []],
        };

        if ($actionRequired) {
            $score = max(50, $score + 10);
        }

        if ($actionRequired && $this->hasStrongTimingSignal($message->getSubject().' '.$message->getSnippet())) {
            $score += 25;
            $reasons[] = 'Un délai ou un signal d’urgence explicite a été détecté.';
        }

        if ($actionRequired && $ageHours >= 48) {
            $score += 20;
            $reasons[] = 'Ce message attend une action depuis plus de 48 h.';
        } elseif ($actionRequired && $ageHours >= 24) {
            $score += 10;
            $reasons[] = 'Ce message attend une action depuis plus de 24 h.';
        }

        $level = match (true) {
            $score >= 80 => 'URGENT',
            $score >= 50 => 'PRIORITY',
            default => 'NORMAL',
        };

        return $this->result(
            $level,
            $actionRequired,
            array_values(array_unique($reasons)),
            $actionRequired ? $this->recommendedAction($message->getCategory()) : null,
            $ageHours,
        );
    }

    private function hasStrongTimingSignal(string $value): bool
    {
        $text = $this->normalize($value);
        $signals = [
            'urgent',
            'asap',
            'des que possible',
            "aujourd'hui",
            'avant ce soir',
            'dans les 24 h',
            'dans les 24h',
            'sous 24 h',
            'sous 24h',
            'today',
            'before end of day',
            'within 24 hours',
            'tomorrow',
            'demain',
        ];

        foreach ($signals as $signal) {
            if (str_contains($text, $this->normalize($signal))) {
                return true;
            }
        }

        return false;
    }

    private function recommendedAction(string $category): string
    {
        return match ($category) {
            'INTERVIEW_REQUEST' => 'Planifier ou répondre à l’entretien',
            'INFORMATION_REQUEST' => 'Répondre aux informations demandées',
            'RECRUITER_OPPORTUNITY' => 'Voir et répondre à la proposition',
            'APPLICATION_REPLY' => 'Ouvrir et répondre au recruteur',
            default => 'Traiter ce message',
        };
    }

    /**
     * @param 'URGENT'|'PRIORITY'|'NORMAL' $level
     * @param list<string> $reasons
     * @return array{
     *   level: 'URGENT'|'PRIORITY'|'NORMAL',
     *   label: string,
     *   actionRequired: bool,
     *   reasons: list<string>,
     *   recommendedAction: string|null,
     *   ageHours: float
     * }
     */
    private function result(
        string $level,
        bool $actionRequired,
        array $reasons,
        ?string $recommendedAction,
        float $ageHours,
    ): array {
        return [
            'level' => $level,
            'label' => match ($level) {
                'URGENT' => 'Urgent',
                'PRIORITY' => 'Prioritaire',
                default => 'Normal',
            },
            'actionRequired' => $actionRequired,
            'reasons' => $reasons,
            'recommendedAction' => $recommendedAction,
            'ageHours' => round($ageHours, 1),
        ];
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
