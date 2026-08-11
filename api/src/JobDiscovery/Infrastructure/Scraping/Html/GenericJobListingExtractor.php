<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Html;

final class GenericJobListingExtractor
{
    private const MAX_CANDIDATES = 50;

    /** @return list<array<string, mixed>> */
    public function extract(string $html, string $baseUrl, string $sourceName): array
    {
        $document = $this->document($html);
        $xpath = new \DOMXPath($document);
        $candidates = $this->jsonLdCandidates($xpath, $baseUrl, $sourceName);

        if ($candidates !== []) {
            return array_slice($candidates, 0, self::MAX_CANDIDATES);
        }

        return array_slice($this->linkCandidates($xpath, $baseUrl, $sourceName), 0, self::MAX_CANDIDATES);
    }

    /** @return list<array<string, mixed>> */
    private function jsonLdCandidates(\DOMXPath $xpath, string $baseUrl, string $sourceName): array
    {
        $scripts = $xpath->query('//script[contains(translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "ld+json")]');
        if (!$scripts instanceof \DOMNodeList) {
            return [];
        }

        $offers = [];
        $seen = [];

        foreach ($scripts as $script) {
            $json = trim($script->textContent ?? '');
            if ($json === '') {
                continue;
            }

            try {
                $decoded = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            foreach ($this->jobPostingObjects($decoded) as $posting) {
                $candidate = $this->candidateFromJobPosting($posting, $baseUrl, $sourceName);
                if ($candidate === null) {
                    continue;
                }

                $key = (string) $candidate['externalId'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $offers[] = $candidate;
            }
        }

        return $offers;
    }

    /**
     * @param mixed $value
     * @return list<array<string, mixed>>
     */
    private function jobPostingObjects(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $matches = [];
        if ($this->hasJobPostingType($value)) {
            $matches[] = $value;
        }

        foreach ($value as $child) {
            if (!is_array($child)) {
                continue;
            }
            foreach ($this->jobPostingObjects($child) as $posting) {
                $matches[] = $posting;
            }
        }

        return $matches;
    }

    /** @param array<string|int, mixed> $value */
    private function hasJobPostingType(array $value): bool
    {
        $type = $value['@type'] ?? null;
        if (is_string($type)) {
            return strcasecmp(trim($type), 'JobPosting') === 0;
        }
        if (!is_array($type)) {
            return false;
        }

        foreach ($type as $item) {
            if (is_string($item) && strcasecmp(trim($item), 'JobPosting') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $posting
     * @return array<string, mixed>|null
     */
    private function candidateFromJobPosting(array $posting, string $baseUrl, string $sourceName): ?array
    {
        $title = $this->clean((string) ($posting['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $sourceUrl = $this->resolveSameHostUrl($baseUrl, $this->stringValue($posting['url'] ?? null)) ?? $baseUrl;
        $company = $this->organizationName($posting['hiringOrganization'] ?? null) ?: $sourceName;
        $location = $this->location($posting);
        $contractType = $this->employmentType($posting['employmentType'] ?? null);
        $workMode = $this->workMode($posting);
        $description = $this->clean((string) ($posting['description'] ?? ''));
        $publishedAt = $this->dateValue($posting['datePosted'] ?? null);
        $salary = $this->salary($posting['baseSalary'] ?? null);
        $identifier = $this->identifier($posting['identifier'] ?? null);
        $externalId = $identifier !== ''
            ? mb_substr($identifier, 0, 180)
            : 'jsonld-'.substr(hash('sha256', $sourceUrl.'|'.$title.'|'.$company), 0, 32);

        return [
            'source' => $sourceName,
            'sourceUrl' => $sourceUrl,
            'externalId' => $externalId,
            'title' => $title,
            'company' => $company,
            'location' => $location,
            'contractType' => $contractType,
            'workMode' => $workMode,
            'language' => $this->language($posting),
            'description' => mb_substr($description, 0, 50_000),
            'publishedAt' => $publishedAt,
            'salaryMin' => $salary['salaryMin'],
            'salaryMax' => $salary['salaryMax'],
            'tjmMin' => $salary['tjmMin'],
            'tjmMax' => $salary['tjmMax'],
            'rawData' => [
                'extractionMethod' => 'JSON_LD',
                'schemaType' => 'JobPosting',
                'identifier' => $identifier !== '' ? $identifier : null,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function linkCandidates(\DOMXPath $xpath, string $baseUrl, string $sourceName): array
    {
        $links = $xpath->query('//a[@href]');
        if (!$links instanceof \DOMNodeList) {
            return [];
        }

        $offers = [];
        $seen = [];

        foreach ($links as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }

            $url = $this->resolveSameHostUrl($baseUrl, trim($link->getAttribute('href')));
            if ($url === null || !$this->looksLikeJobUrl($url)) {
                continue;
            }

            $title = $this->candidateTitle($xpath, $link);
            if ($title === '' || $this->genericLinkText($title)) {
                continue;
            }

            $key = strtolower($url);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $offers[] = [
                'source' => $sourceName,
                'sourceUrl' => $url,
                'externalId' => 'link-'.substr(hash('sha256', $url), 0, 32),
                'title' => $title,
                'company' => $sourceName,
                'location' => '',
                'contractType' => '',
                'workMode' => '',
                'language' => 'fr',
                'description' => '',
                'publishedAt' => null,
                'salaryMin' => null,
                'salaryMax' => null,
                'tjmMin' => null,
                'tjmMax' => null,
                'rawData' => [
                    'extractionMethod' => 'JOB_LINK',
                    'needsDetailFetch' => true,
                ],
            ];
        }

        return $offers;
    }

    private function candidateTitle(\DOMXPath $xpath, \DOMElement $link): string
    {
        $text = $this->clean($link->textContent ?? '');
        if ($text !== '' && !$this->genericLinkText($text) && mb_strlen($text) >= 5) {
            return mb_substr($text, 0, 240);
        }

        $node = $link;
        for ($level = 0; $level < 3 && $node->parentNode instanceof \DOMElement; ++$level) {
            $node = $node->parentNode;
            $headings = $xpath->query('.//*[self::h1 or self::h2 or self::h3 or self::h4]', $node);
            if (!$headings instanceof \DOMNodeList || $headings->length === 0) {
                continue;
            }
            $heading = $this->clean($headings->item(0)?->textContent ?? '');
            if ($heading !== '') {
                return mb_substr($heading, 0, 240);
            }
        }

        return '';
    }

    private function genericLinkText(string $value): bool
    {
        $value = $this->normalize($value);
        $value = trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value);

        return preg_match('/^(voir|voir l offre|voir le poste|postuler|en savoir plus|details?|detail|candidater|apply|learn more)$/', $value) === 1;
    }

    private function looksLikeJobUrl(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        if ($path === '' || $path === '/') {
            return false;
        }

        if (preg_match('~/(job|jobs|offre|offres|emploi|emplois|mission|missions|position|positions|vacancy|vacancies|career|careers)/[^/?#]+~iu', $path) === 1) {
            return true;
        }

        return preg_match('~/(job|offre|emploi|mission|position|vacancy)[-_][^/?#]+~iu', $path) === 1;
    }

    private function resolveSameHostUrl(string $baseUrl, ?string $href): ?string
    {
        $href = trim((string) $href);
        if ($href === '' || str_starts_with($href, '#') || preg_match('~^(?:javascript|mailto|tel):~i', $href) === 1) {
            return null;
        }

        $base = parse_url($baseUrl);
        $baseHost = strtolower((string) ($base['host'] ?? ''));
        if ($baseHost === '' || strtolower((string) ($base['scheme'] ?? '')) !== 'https') {
            return null;
        }

        if (str_starts_with($href, '//')) {
            $href = 'https:'.$href;
        }

        if (preg_match('~^https://~i', $href) === 1) {
            $url = $href;
        } elseif (str_starts_with($href, '/')) {
            $url = 'https://'.$baseHost.$href;
        } else {
            $basePath = (string) ($base['path'] ?? '/');
            $directory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath).'/';
            $url = 'https://'.$baseHost.$directory.$href;
        }

        $parts = parse_url($url);
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== $baseHost) {
            return null;
        }

        $path = $this->normalizePath((string) ($parts['path'] ?? '/'));
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return 'https://'.$baseHost.$path.$query;
    }

    private function normalizePath(string $path): string
    {
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return '/'.implode('/', $segments);
    }

    /** @param array<string, mixed> $posting */
    private function location(array $posting): string
    {
        if (stripos($this->stringValue($posting['jobLocationType'] ?? null) ?? '', 'TELECOMMUTE') !== false) {
            return 'Télétravail';
        }

        $locations = $posting['jobLocation'] ?? null;
        if (!is_array($locations)) {
            return '';
        }
        if (array_is_list($locations)) {
            $locations = $locations[0] ?? [];
        }
        if (!is_array($locations)) {
            return '';
        }

        $address = $locations['address'] ?? $locations;
        if (!is_array($address)) {
            return $this->clean((string) $address);
        }

        $parts = [];
        foreach (['addressLocality', 'addressRegion', 'addressCountry'] as $key) {
            $value = $address[$key] ?? null;
            if (is_array($value)) {
                $value = $value['name'] ?? null;
            }
            $clean = $this->clean((string) ($value ?? ''));
            if ($clean !== '' && !in_array($clean, $parts, true)) {
                $parts[] = $clean;
            }
        }

        return implode(', ', $parts);
    }

    /** @param array<string, mixed> $posting */
    private function workMode(array $posting): string
    {
        $locationType = strtoupper($this->stringValue($posting['jobLocationType'] ?? null) ?? '');
        if (str_contains($locationType, 'TELECOMMUTE')) {
            return 'Télétravail';
        }

        return '';
    }

    private function employmentType(mixed $value): string
    {
        if (is_string($value)) {
            return mb_substr($this->clean($value), 0, 120);
        }
        if (!is_array($value)) {
            return '';
        }

        $values = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }
            $clean = $this->clean($item);
            if ($clean !== '') {
                $values[] = $clean;
            }
        }

        return mb_substr(implode(', ', array_unique($values)), 0, 120);
    }

    private function organizationName(mixed $value): string
    {
        if (is_string($value)) {
            return $this->clean($value);
        }
        if (!is_array($value)) {
            return '';
        }

        return $this->clean((string) ($value['name'] ?? ''));
    }

    private function identifier(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return $this->clean((string) $value);
        }
        if (!is_array($value)) {
            return '';
        }

        foreach (['value', 'name', '@id'] as $key) {
            if (isset($value[$key]) && (is_string($value[$key]) || is_numeric($value[$key]))) {
                $identifier = $this->clean((string) $value[$key]);
                if ($identifier !== '') {
                    return $identifier;
                }
            }
        }

        return '';
    }

    /** @return array{salaryMin: int|null, salaryMax: int|null, tjmMin: int|null, tjmMax: int|null} */
    private function salary(mixed $baseSalary): array
    {
        $empty = ['salaryMin' => null, 'salaryMax' => null, 'tjmMin' => null, 'tjmMax' => null];
        if (!is_array($baseSalary)) {
            return $empty;
        }

        $unit = strtoupper($this->stringValue($baseSalary['unitText'] ?? null) ?? '');
        $value = $baseSalary['value'] ?? null;
        if (is_array($value)) {
            $unit = strtoupper($this->stringValue($value['unitText'] ?? null) ?? $unit);
        }
        if (!is_array($value)) {
            $value = $baseSalary;
        }

        $min = $this->numericValue($value['minValue'] ?? $value['value'] ?? null);
        $max = $this->numericValue($value['maxValue'] ?? $value['value'] ?? null);
        if ($min === null && $max === null) {
            return $empty;
        }
        $min ??= $max;
        $max ??= $min;

        if (in_array($unit, ['DAY', 'DAILY'], true)) {
            return ['salaryMin' => null, 'salaryMax' => null, 'tjmMin' => $min, 'tjmMax' => $max];
        }
        if (in_array($unit, ['YEAR', 'YEARLY', 'ANNUAL'], true)) {
            return ['salaryMin' => $min, 'salaryMax' => $max, 'tjmMin' => null, 'tjmMax' => null];
        }

        return $empty;
    }

    private function numericValue(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $number = (int) round((float) $value);

        return $number > 0 ? $number : null;
    }

    /** @param array<string, mixed> $posting */
    private function language(array $posting): string
    {
        $language = strtolower($this->stringValue($posting['inLanguage'] ?? null) ?? '');
        if (str_starts_with($language, 'en')) {
            return 'en';
        }

        return 'fr';
    }

    private function dateValue(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value)->format(DATE_ATOM);
        } catch (\Exception) {
            return null;
        }
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : null;
    }

    private function document(string $html): \DOMDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8">'.$html,
                LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            throw new \RuntimeException('La page HTML ne contient pas un document exploitable.');
        }

        return $document;
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function normalize(string $value): string
    {
        $value = $this->clean($value);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return strtolower($ascii === false ? $value : $ascii);
    }
}
