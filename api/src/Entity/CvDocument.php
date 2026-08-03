<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CvDocument
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 180)] private string $name;
    #[ORM\Column(length: 255)] private string $originalName;
    #[ORM\Column(length: 255)] private string $storedName;
    #[ORM\Column(length: 16)] private string $language;
    #[ORM\Column(length: 80)] private string $category = 'Général';
    #[ORM\Column(type: 'json')] private array $tags = [];
    #[ORM\Column] private bool $active = true;
    #[ORM\Column] private bool $defaultForLanguage = false;
    #[ORM\Column(length: 120)] private string $mimeType;
    #[ORM\Column] private int $size;
    #[ORM\Column] private \DateTimeImmutable $createdAt;

    public function __construct(string $name, string $originalName, string $storedName, string $language, string $mimeType, int $size)
    {
        $this->name = $name;
        $this->originalName = $originalName;
        $this->storedName = $storedName;
        $this->language = $language;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getStoredName(): string { return $this->storedName; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getLanguage(): string { return $this->language; }
    public function getTags(): array { return $this->tags; }
    public function isActive(): bool { return $this->active; }
    public function isDefaultForLanguage(): bool { return $this->defaultForLanguage; }

    public function configure(array $data): self
    {
        if (isset($data['name'])) $this->name = trim((string) $data['name']);
        if (isset($data['category'])) $this->category = trim((string) $data['category']);
        if (isset($data['tags'])) $this->tags = is_array($data['tags']) ? array_values($data['tags']) : [];
        if (array_key_exists('active', $data)) $this->active = (bool) $data['active'];
        if (array_key_exists('defaultForLanguage', $data)) $this->defaultForLanguage = (bool) $data['defaultForLanguage'];
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'originalName' => $this->originalName,
            'language' => $this->language,
            'category' => $this->category,
            'tags' => $this->tags,
            'active' => $this->active,
            'defaultForLanguage' => $this->defaultForLanguage,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'downloadUrl' => '/api/cvs/'.$this->id.'/download',
            'createdAt' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
