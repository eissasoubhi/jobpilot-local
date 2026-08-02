<?php

namespace App\Service;

use App\Entity\Positioning;
use Doctrine\ORM\EntityManagerInterface;

final class DuplicatePositioningDetector
{
    private const BLOCKING_STATUSES = ['AGREEMENT_GIVEN','SUBMITTED_TO_CLIENT','WAITING_CLIENT','INTERVIEW_SCHEDULED','ACCEPTED'];

    public function __construct(private EntityManagerInterface $em) {}

    public function check(array $candidate): array
    {
        $existing = $this->em->getRepository(Positioning::class)->findAll();
        $matches = [];
        foreach ($existing as $item) {
            if (!in_array($item->getStatus(), self::BLOCKING_STATUSES, true)) continue;
            $score = 0;
            $reasons = [];
            $reference = trim((string) ($candidate['callForTenderReference'] ?? ''));
            if ($reference !== '' && $item->getCallForTenderReference() !== null && mb_strtolower($reference) === mb_strtolower($item->getCallForTenderReference())) {
                $score = 100;
                $reasons[] = 'Même référence d’appel d’offres';
            } else {
                if ($this->similar((string) ($candidate['finalClient'] ?? ''), $item->getFinalClient()) >= 0.8) { $score += 45; $reasons[] = 'Client final similaire'; }
                if ($this->similar((string) ($candidate['missionTitle'] ?? ''), $item->getMissionTitle()) >= 0.65) { $score += 35; $reasons[] = 'Intitulé similaire'; }
                if ($this->similar((string) ($candidate['description'] ?? ''), $item->getDescription()) >= 0.55) { $score += 20; $reasons[] = 'Description similaire'; }
            }
            if ($score >= 60) $matches[] = ['score' => min(100, $score), 'reasons' => $reasons, 'positioning' => $item->toArray()];
        }
        usort($matches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        return ['duplicate' => $matches !== [], 'matches' => $matches];
    }

    private function similar(string $a, string $b): float
    {
        $a = $this->normalise($a); $b = $this->normalise($b);
        if ($a === '' || $b === '') return 0.0;
        similar_text($a, $b, $percent);
        return $percent / 100;
    }

    private function normalise(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim(mb_strtolower($value))) ?? '';
    }
}
