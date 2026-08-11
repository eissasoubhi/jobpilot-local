<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'custom_scraper_source')]
#[ORM\UniqueConstraint(name: 'uniq_custom_scraper_source_listing_url', columns: ['listing_url'])]
#[ORM\Index(columns: ['enabled'], name: 'idx_custom_scraper_source_enabled')]
final class CustomScraperSource
{
    public const MODE_AUTO = 'AUTO';
    public const MODE_HTTP = 'HTTP';
    public const MODE_BROWSER = 'BROWSER';

    private const MAX_SEARCH_KEYWORDS = 20;
    private const MAX_SEARCH_KEYWORD_LENGTH = 80;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $domain;

    #[ORM\Column(length: 2048)]
    private string $listingUrl;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $detailExampleUrl = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $searchUrlTemplate = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $searchKeywords = [];

    #[ORM\Column(length: 16)]
    private string $mode = self::MODE_AUTO;

    #[ORM\Column]
    private bool $enabled = false;

    #[ORM\Column]
    private bool $authorizationConfirmed = false;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $authorizationCheckedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $authorizationReference = null;

    #[ORM\Column]
    private int $syncIntervalMinutes = 360;

    #[ORM\Column]
    private int $maxPages = 5;

    #[ORM\Column]
    private int $maxDetails = 20;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, string $listingUrl)
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->name = $this->normalizeName($name);
        $this->setListingUrl($listingUrl);
    }

    /** @param array<string, mixed> $data */
    public function fill(array $data): self
    {
        if (array_key_exists('name', $data)) {
            $this->name = $this->normalizeName((string) $data['name']);
        }

        if (array_key_exists('listingUrl', $data)) {
            $this->setListingUrl((string) $data['listingUrl']);
        }

        if (array_key_exists('detailExampleUrl', $data)) {
            $value = trim((string) ($data['detailExampleUrl'] ?? ''));
            $this->detailExampleUrl = $value === '' ? null : $this->normalizeHttpsUrl($value, true);
        }

        if (array_key_exists('searchUrlTemplate', $data)) {
            $this->searchUrlTemplate = $this->normalizeSearchUrlTemplate($data['searchUrlTemplate']);
        }

        if (array_key_exists('searchKeywords', $data)) {
            $this->searchKeywords = $this->normalizeSearchKeywords($data['searchKeywords']);
        }

        if (array_key_exists('mode', $data)) {
            $mode = strtoupper(trim((string) $data['mode']));
            if (!in_array($mode, [self::MODE_AUTO, self::MODE_HTTP, self::MODE_BROWSER], true)) {
                throw new \InvalidArgumentException('Le mode doit être AUTO, HTTP ou BROWSER.');
            }
            $this->mode = $mode;
        }

        if (array_key_exists('authorizationConfirmed', $data)) {
            $this->authorizationConfirmed = (bool) $data['authorizationConfirmed'];
            if (!$this->authorizationConfirmed) {
                $this->enabled = false;
            }
        }

        if (array_key_exists('authorizationCheckedAt', $data)) {
            $value = trim((string) ($data['authorizationCheckedAt'] ?? ''));
            $this->authorizationCheckedAt = $value === '' ? null : $this->parseDate($value);
        }

        if (array_key_exists('authorizationReference', $data)) {
            $value = trim((string) ($data['authorizationReference'] ?? ''));
            $this->authorizationReference = $value === '' ? null : mb_substr($value, 0, 5000);
        }

        if (array_key_exists('syncIntervalMinutes', $data)) {
            $this->syncIntervalMinutes = min(10080, max(60, (int) $data['syncIntervalMinutes']));
        }
        if (array_key_exists('maxPages', $data)) {
            $this->maxPages = min(20, max(1, (int) $data['maxPages']));
        }
        if (array_key_exists('maxDetails', $data)) {
            $this->maxDetails = min(100, max(0, (int) $data['maxDetails']));
        }

        if ($this->authorizationConfirmed && $this->authorizationCheckedAt === null) {
            $this->authorizationCheckedAt = new \DateTimeImmutable('today');
        }

        if (array_key_exists('enabled', $data)) {
            $enabled = (bool) $data['enabled'];
            if ($enabled && !$this->authorizationConfirmed) {
                throw new \InvalidArgumentException('Confirme d’abord que tu as vérifié l’autorisation de collecte pour ce site.');
            }
            $this->enabled = $enabled;
        }

        if ($this->detailExampleUrl !== null) {
            $detailHost = strtolower((string) parse_url($this->detailExampleUrl, PHP_URL_HOST));
            if ($detailHost !== $this->domain) {
                throw new \InvalidArgumentException('L’URL de détail doit utiliser le même domaine que la liste des offres.');
            }
        }

        if ($this->searchUrlTemplate !== null) {
            $this->searchUrlTemplate = $this->normalizeSearchUrlTemplate($this->searchUrlTemplate);
        }
        if ($this->searchKeywords !== [] && $this->searchUrlTemplate === null) {
            throw new \InvalidArgumentException('Une URL de recherche avec {keyword} est obligatoire lorsque des mots-clés sont configurés.');
        }

        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'listingUrl' => $this->listingUrl,
            'detailExampleUrl' => $this->detailExampleUrl,
            'searchUrlTemplate' => $this->searchUrlTemplate,
            'searchKeywords' => $this->searchKeywords,
            'mode' => $this->mode,
            'enabled' => $this->enabled,
            'authorizationConfirmed' => $this->authorizationConfirmed,
            'authorizationCheckedAt' => $this->authorizationCheckedAt?->format('Y-m-d'),
            'authorizationReference' => $this->authorizationReference,
            'syncIntervalMinutes' => $this->syncIntervalMinutes,
            'maxPages' => $this->maxPages,
            'maxDetails' => $this->maxDetails,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    private function setListingUrl(string $url): void
    {
        $normalized = $this->normalizeHttpsUrl($url, false);
        $host = strtolower((string) parse_url($normalized, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            throw new \InvalidArgumentException('Le domaine doit être un hôte HTTPS public.');
        }

        $this->listingUrl = $normalized;
        $this->domain = $host;
    }

    private function normalizeHttpsUrl(string $value, bool $allowQuery): string
    {
        $value = trim($value);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Une URL HTTPS valide est obligatoire.');
        }

        $parts = parse_url($value);
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new \InvalidArgumentException('Seules les URL HTTPS sont acceptées.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('Les URL avec identifiants ou fragment ne sont pas acceptées.');
        }
        if (!$allowQuery && isset($parts['query']) && strlen((string) $parts['query']) > 1500) {
            throw new \InvalidArgumentException('La requête de l’URL de liste est trop longue.');
        }

        return mb_substr($value, 0, 2048);
    }

    private function normalizeSearchUrlTemplate(mixed $value): ?string
    {
        $template = trim((string) ($value ?? ''));
        if ($template === '') {
            return null;
        }
        if (!str_contains($template, '{keyword}')) {
            throw new \InvalidArgumentException('L’URL de recherche doit contenir le placeholder {keyword}.');
        }

        $unknownPlaceholderCandidate = str_replace('{keyword}', '', $template);
        if (preg_match('/\{[^}]+\}/', $unknownPlaceholderCandidate) === 1) {
            throw new \InvalidArgumentException('Seul le placeholder {keyword} est accepté dans l’URL de recherche.');
        }

        $validationUrl = str_replace('{keyword}', 'php', $template);
        $normalized = $this->normalizeHttpsUrl($validationUrl, true);
        $host = strtolower((string) parse_url($normalized, PHP_URL_HOST));
        if ($host !== $this->domain) {
            throw new \InvalidArgumentException('L’URL de recherche doit utiliser le même domaine que la liste des offres.');
        }
        if (mb_strlen($template) > 2048) {
            throw new \InvalidArgumentException('L’URL de recherche est trop longue.');
        }

        return $template;
    }

    /** @return list<string> */
    private function normalizeSearchKeywords(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Les mots-clés de recherche doivent être fournis sous forme de liste.');
        }

        $keywords = [];
        $seen = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                throw new \InvalidArgumentException('Chaque mot-clé de recherche doit être une chaîne de caractères.');
            }
            $keyword = trim((string) $item);
            if ($keyword === '') {
                continue;
            }
            if (mb_strlen($keyword) > self::MAX_SEARCH_KEYWORD_LENGTH) {
                throw new \InvalidArgumentException(sprintf('Un mot-clé de recherche ne peut pas dépasser %d caractères.', self::MAX_SEARCH_KEYWORD_LENGTH));
            }

            $key = mb_strtolower($keyword);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $keywords[] = $keyword;

            if (count($keywords) > self::MAX_SEARCH_KEYWORDS) {
                throw new \InvalidArgumentException(sprintf('Une source ne peut pas contenir plus de %d mots-clés de recherche.', self::MAX_SEARCH_KEYWORDS));
            }
        }

        return $keywords;
    }

    private function normalizeName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException('Le nom du site est obligatoire.');
        }

        return mb_substr($value, 0, 120);
    }

    private function parseDate(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new \InvalidArgumentException('La date de vérification doit être au format YYYY-MM-DD.');
        }

        return $date;
    }
}
