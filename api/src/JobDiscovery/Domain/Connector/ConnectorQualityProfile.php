<?php

declare(strict_types=1);

namespace App\JobDiscovery\Domain\Connector;

final readonly class ConnectorQualityProfile
{
    /**
     * @param list<string> $requiredFields
     * @param list<string> $recommendedFields
     */
    public function __construct(
        public string $name,
        public array $requiredFields,
        public array $recommendedFields,
        public float $brokenRequiredCompleteness = 80.0,
        public float $watchRecommendedCompleteness = 50.0,
    ) {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Le nom du profil de qualité est obligatoire.');
        }
        if ($this->requiredFields === []) {
            throw new \InvalidArgumentException('Un profil de qualité doit déclarer au moins un champ obligatoire.');
        }
        if (array_diff($this->requiredFields, array_unique($this->requiredFields)) !== []) {
            throw new \InvalidArgumentException('Les champs obligatoires du profil de qualité doivent être uniques.');
        }
        if (array_intersect($this->requiredFields, $this->recommendedFields) !== []) {
            throw new \InvalidArgumentException('Un champ ne peut pas être obligatoire et recommandé à la fois.');
        }
        foreach ([$this->brokenRequiredCompleteness, $this->watchRecommendedCompleteness] as $threshold) {
            if ($threshold < 0.0 || $threshold > 100.0) {
                throw new \InvalidArgumentException('Les seuils de qualité doivent être compris entre 0 et 100.');
            }
        }
    }

    public static function default(): self
    {
        return new self(
            'default-v1',
            ['externalId', 'title', 'description'],
            ['company', 'sourceUrl', 'location', 'contractType', 'publishedAt'],
        );
    }
}
