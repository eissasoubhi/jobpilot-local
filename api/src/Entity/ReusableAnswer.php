<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reusable_answer')]
final class ReusableAnswer
{
    public const SOURCE_STATIC = 'STATIC';
    public const SOURCE_PROFILE = 'PROFILE';

    public const TYPE_TEXT = 'TEXT';
    public const TYPE_NUMBER = 'NUMBER';
    public const TYPE_BOOLEAN = 'BOOLEAN';
    public const TYPE_CHOICE = 'CHOICE';
    public const TYPE_MULTI_CHOICE = 'MULTI_CHOICE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'answer_key', length: 120, unique: true)]
    private string $key;

    #[ORM\Column(length: 180)]
    private string $label;

    #[ORM\Column(length: 80)]
    private string $category = 'CUSTOM';

    #[ORM\Column(length: 20)]
    private string $valueSource = self::SOURCE_STATIC;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $profilePath = null;

    #[ORM\Column(length: 20)]
    private string $answerType = self::TYPE_TEXT;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $answerFr = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $answerEn = null;

    #[ORM\Column(type: 'json')]
    private array $questionPatterns = ['fr' => [], 'en' => []];

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private bool $sensitive = false;

    #[ORM\Column]
    private bool $autoFillAllowed = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $key, string $label)
    {
        $key = strtolower(trim($key));
        $label = trim($label);

        if (preg_match('/^[a-z0-9][a-z0-9_.-]{1,119}$/', $key) !== 1) {
            throw new \InvalidArgumentException('La clé de réponse doit contenir 2 à 120 caractères alphanumériques, tirets, points ou underscores.');
        }
        if ($label === '' || mb_strlen($label) > 180) {
            throw new \InvalidArgumentException('Le libellé de réponse est obligatoire et limité à 180 caractères.');
        }

        $this->key = $key;
        $this->label = $label;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValueSource(): string
    {
        return $this->valueSource;
    }

    public function getProfilePath(): ?string
    {
        return $this->profilePath;
    }

    public function getAnswerFr(): ?string
    {
        return $this->answerFr;
    }

    public function getAnswerEn(): ?string
    {
        return $this->answerEn;
    }

    /** @return array{fr: list<string>, en: list<string>} */
    public function getQuestionPatterns(): array
    {
        return $this->questionPatterns;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isAutoFillAllowed(): bool
    {
        return $this->autoFillAllowed;
    }

    /** @param array<string, mixed> $data */
    public function fill(array $data): self
    {
        if (array_key_exists('label', $data)) {
            $label = trim((string) $data['label']);
            if ($label === '' || mb_strlen($label) > 180) {
                throw new \InvalidArgumentException('Le libellé de réponse est obligatoire et limité à 180 caractères.');
            }
            $this->label = $label;
        }

        if (array_key_exists('category', $data)) {
            $category = strtoupper(trim((string) $data['category']));
            if ($category === '' || mb_strlen($category) > 80) {
                throw new \InvalidArgumentException('La catégorie est invalide.');
            }
            $this->category = $category;
        }

        if (array_key_exists('valueSource', $data)) {
            $source = strtoupper(trim((string) $data['valueSource']));
            if (!in_array($source, [self::SOURCE_STATIC, self::SOURCE_PROFILE], true)) {
                throw new \InvalidArgumentException('La source doit être STATIC ou PROFILE.');
            }
            $this->valueSource = $source;
        }

        if (array_key_exists('profilePath', $data)) {
            $this->profilePath = $this->optionalSingleLine($data['profilePath'], 180);
        }

        if (array_key_exists('answerType', $data)) {
            $type = strtoupper(trim((string) $data['answerType']));
            if (!in_array($type, [self::TYPE_TEXT, self::TYPE_NUMBER, self::TYPE_BOOLEAN, self::TYPE_CHOICE, self::TYPE_MULTI_CHOICE], true)) {
                throw new \InvalidArgumentException('Le type de réponse est invalide.');
            }
            $this->answerType = $type;
        }

        if (array_key_exists('answerFr', $data)) {
            $this->answerFr = $this->optionalText($data['answerFr']);
        }
        if (array_key_exists('answerEn', $data)) {
            $this->answerEn = $this->optionalText($data['answerEn']);
        }

        if (array_key_exists('questionPatterns', $data)) {
            $patterns = is_array($data['questionPatterns']) ? $data['questionPatterns'] : [];
            $this->questionPatterns = [
                'fr' => $this->normalizePatterns($patterns['fr'] ?? []),
                'en' => $this->normalizePatterns($patterns['en'] ?? []),
            ];
        }

        foreach (['enabled', 'sensitive', 'autoFillAllowed'] as $field) {
            if (array_key_exists($field, $data)) {
                $this->{$field} = filter_var($data[$field], FILTER_VALIDATE_BOOL);
            }
        }

        if ($this->valueSource === self::SOURCE_PROFILE && $this->profilePath === null) {
            throw new \InvalidArgumentException('Une réponse liée au profil doit définir profilePath.');
        }
        if ($this->sensitive && !array_key_exists('autoFillAllowed', $data) && $this->autoFillAllowed) {
            $this->autoFillAllowed = false;
        }

        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'category' => $this->category,
            'valueSource' => $this->valueSource,
            'profilePath' => $this->profilePath,
            'answerType' => $this->answerType,
            'answerFr' => $this->answerFr,
            'answerEn' => $this->answerEn,
            'questionPatterns' => $this->questionPatterns,
            'enabled' => $this->enabled,
            'sensitive' => $this->sensitive,
            'autoFillAllowed' => $this->autoFillAllowed,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    private function optionalSingleLine(mixed $value, int $maxLength): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maxLength || preg_match('/[\r\n\0]/u', $value) === 1) {
            throw new \InvalidArgumentException('La valeur contient des caractères invalides ou est trop longue.');
        }

        return $value;
    }

    private function optionalText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > 5000 || str_contains($value, "\0")) {
            throw new \InvalidArgumentException('Une réponse ne peut pas dépasser 5000 caractères.');
        }

        return $value;
    }

    /** @return list<string> */
    private function normalizePatterns(mixed $patterns): array
    {
        if (!is_array($patterns)) {
            return [];
        }

        $normalized = [];
        foreach ($patterns as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern !== '' && mb_strlen($pattern) <= 500) {
                $normalized[] = $pattern;
            }
        }

        return array_values(array_unique($normalized));
    }
}
