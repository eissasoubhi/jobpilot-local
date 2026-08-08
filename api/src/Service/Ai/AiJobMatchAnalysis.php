<?php

declare(strict_types=1);

namespace App\Service\Ai;

final readonly class AiJobMatchAnalysis
{
    /** @var list<string> */
    private const PHP_RELEVANCE_VALUES = [
        'PRIMARY',
        'ALTERNATIVE',
        'MIXED_REQUIRED',
        'SECONDARY',
        'CONTEXTUAL',
        'ABSENT',
        'UNCLEAR',
    ];

    /**
     * @param list<string> $primaryStack
     * @param list<string> $secondaryStack
     * @param list<string> $mustHaves
     * @param list<string> $niceToHaves
     * @param list<string> $missingMustHaves
     * @param list<string> $conflicts
     */
    public function __construct(
        public int $score,
        public float $confidence,
        public string $decision,
        public string $primaryRole,
        public array $primaryStack,
        public array $secondaryStack,
        public array $mustHaves,
        public array $niceToHaves,
        public array $missingMustHaves,
        public array $conflicts,
        public string $explanation,
        public string $phpRelevance = 'UNCLEAR',
    ) {
        if ($score < 0 || $score > 100) {
            throw new \InvalidArgumentException('AI matching score must be between 0 and 100.');
        }
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new \InvalidArgumentException('AI matching confidence must be between 0 and 1.');
        }
        if (!in_array($decision, ['MATCH', 'REVIEW', 'NO_MATCH'], true)) {
            throw new \InvalidArgumentException('Unsupported AI matching decision.');
        }
        if (!in_array($phpRelevance, self::PHP_RELEVANCE_VALUES, true)) {
            throw new \InvalidArgumentException('Unsupported PHP relevance classification.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        foreach (['score', 'confidence', 'decision', 'primaryRole', 'primaryStack', 'secondaryStack', 'mustHaves', 'niceToHaves', 'missingMustHaves', 'conflicts', 'explanation'] as $required) {
            if (!array_key_exists($required, $data)) {
                throw new \InvalidArgumentException('Missing AI matching field: '.$required);
            }
        }

        if (!is_numeric($data['score']) || !is_numeric($data['confidence'])) {
            throw new \InvalidArgumentException('AI matching score and confidence must be numeric.');
        }

        foreach (['decision', 'primaryRole', 'explanation'] as $field) {
            if (!is_string($data[$field])) {
                throw new \InvalidArgumentException('AI matching field must be a string: '.$field);
            }
        }

        $phpRelevance = $data['phpRelevance'] ?? 'UNCLEAR';
        if (!is_string($phpRelevance)) {
            throw new \InvalidArgumentException('AI matching field must be a string: phpRelevance');
        }
        $phpRelevance = strtoupper(trim($phpRelevance));

        $lists = [];
        foreach (['primaryStack', 'secondaryStack', 'mustHaves', 'niceToHaves', 'missingMustHaves', 'conflicts'] as $field) {
            if (!is_array($data[$field])) {
                throw new \InvalidArgumentException('AI matching field must be an array: '.$field);
            }
            $lists[$field] = array_values(array_map(
                static function (mixed $value) use ($field): string {
                    if (!is_string($value)) {
                        throw new \InvalidArgumentException('AI matching list contains a non-string value: '.$field);
                    }

                    return trim($value);
                },
                $data[$field],
            ));
        }

        return new self(
            (int) $data['score'],
            (float) $data['confidence'],
            trim($data['decision']),
            trim($data['primaryRole']),
            $lists['primaryStack'],
            $lists['secondaryStack'],
            $lists['mustHaves'],
            $lists['niceToHaves'],
            $lists['missingMustHaves'],
            $lists['conflicts'],
            trim($data['explanation']),
            $phpRelevance,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'confidence' => $this->confidence,
            'decision' => $this->decision,
            'primaryRole' => $this->primaryRole,
            'primaryStack' => $this->primaryStack,
            'secondaryStack' => $this->secondaryStack,
            'mustHaves' => $this->mustHaves,
            'niceToHaves' => $this->niceToHaves,
            'missingMustHaves' => $this->missingMustHaves,
            'conflicts' => $this->conflicts,
            'explanation' => $this->explanation,
            'phpRelevance' => $this->phpRelevance,
        ];
    }

    /** @return list<string> */
    public function toScoreReasons(): array
    {
        $reasons = [sprintf(
            'Analyse IA : %s · confiance %d%%',
            $this->decision,
            (int) round($this->confidence * 100),
        )];

        if ($this->primaryRole !== '') {
            $reasons[] = 'Rôle principal détecté par IA : '.$this->primaryRole;
        }
        if ($this->primaryStack !== []) {
            $reasons[] = 'Stack principale détectée par IA : '.implode(', ', $this->primaryStack);
        }
        $reasons[] = 'Positionnement PHP détecté par IA : '.$this->phpRelevanceLabel();
        if ($this->missingMustHaves !== []) {
            $reasons[] = 'Prérequis principaux manquants : '.implode(', ', $this->missingMustHaves);
        }
        if ($this->conflicts !== []) {
            $reasons[] = 'Conflits détectés : '.implode(' · ', $this->conflicts);
        }
        if ($this->explanation !== '') {
            $reasons[] = 'Explication IA : '.$this->explanation;
        }

        return $reasons;
    }

    private function phpRelevanceLabel(): string
    {
        return match ($this->phpRelevance) {
            'PRIMARY' => 'principal',
            'ALTERNATIVE' => 'alternative principale',
            'MIXED_REQUIRED' => 'requis avec une autre stack principale',
            'SECONDARY' => 'secondaire',
            'CONTEXTUAL' => 'contextuel / legacy',
            'ABSENT' => 'absent',
            default => 'indéterminé',
        };
    }
}
