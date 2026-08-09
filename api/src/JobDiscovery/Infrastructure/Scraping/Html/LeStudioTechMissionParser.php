<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Html;

final class LeStudioTechMissionParser
{
    private const SOURCE_NAME = 'Le Studio Tech';
    private const SOURCE_HOST = 'app.lestudiotech.com';

    /** @return list<array<string, mixed>> */
    public function parseListing(string $html, string $baseUrl): array
    {
        $document = $this->document($html);
        $xpath = new \DOMXPath($document);
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

            $sourceUrl = $this->missionUrl($baseUrl, trim($link->getAttribute('href')));
            if ($sourceUrl === null) {
                continue;
            }

            $text = $this->clean($link->textContent ?? '');
            if ($text === '') {
                continue;
            }

            $externalId = $this->externalId($text, $sourceUrl);
            if (isset($seen[$externalId])) {
                continue;
            }

            $title = $this->heading($xpath, $link);
            if ($title === '') {
                $title = $this->beforeMarker($text, 'Ref');
            }
            if ($title === '') {
                continue;
            }

            $seen[$externalId] = true;
            $tjm = $this->tjm($text);
            $workMode = $this->workMode($text);
            $publishedAt = $this->publishedAt($text);
            $location = $this->between($text, 'Localisation', 'TJM');
            $experience = $this->betweenAny($text, ['Expérience min', 'Experience min'], ['Date de début', 'Date de debut']);
            $startDate = $this->afterAny($text, ['Date de début', 'Date de debut']);

            $offers[] = [
                'source' => self::SOURCE_NAME,
                'sourceUrl' => $sourceUrl,
                'externalId' => $externalId,
                'title' => $title,
                'company' => self::SOURCE_NAME,
                'location' => $location,
                'contractType' => 'Freelance',
                'workMode' => $workMode,
                'language' => 'fr',
                'description' => $text,
                'publishedAt' => $publishedAt,
                'salaryMin' => null,
                'salaryMax' => null,
                'tjmMin' => $tjm,
                'tjmMax' => $tjm,
                'rawData' => [
                    'reference' => $externalId,
                    'publishedAt' => $publishedAt,
                    'location' => $location,
                    'tjm' => $tjm,
                    'workMode' => $workMode,
                    'minimumExperience' => $experience !== '' ? $experience : null,
                    'startDate' => $startDate !== '' ? $startDate : null,
                    'detailEnriched' => false,
                ],
            ];
        }

        return $offers;
    }

    /** @param array<string, mixed> $offer
     *  @return array<string, mixed>|null
     */
    public function enrichDetail(string $html, array $offer): ?array
    {
        $document = $this->document($html);
        $xpath = new \DOMXPath($document);
        $body = $xpath->query('//body')?->item(0);
        $bodyText = $this->clean($body?->textContent ?? $document->textContent ?? '');

        if ($bodyText === '') {
            return $offer;
        }
        if (preg_match('/\bmission\s+termin[ée]e\b/iu', $bodyText) === 1) {
            return null;
        }

        $title = $this->firstHeading($xpath);
        if ($title !== '') {
            $offer['title'] = $title;
        }

        $description = $this->section(
            $bodyText,
            ['Contexte et description de la mission'],
            ['CONTACT', 'Postuler', 'Missions similaires'],
        );
        if ($description !== '') {
            $offer['description'] = mb_substr($description, 0, 50_000);
        }

        $rawData = is_array($offer['rawData'] ?? null) ? $offer['rawData'] : [];
        $rawData['detailEnriched'] = true;
        $offer['rawData'] = $rawData;

        return $offer;
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
            throw new \RuntimeException('La page HTML Le Studio Tech est invalide.');
        }

        return $document;
    }

    private function missionUrl(string $baseUrl, string $href): ?string
    {
        if ($href === '') {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            $url = $href;
        } else {
            $base = parse_url($baseUrl);
            $scheme = strtolower((string) ($base['scheme'] ?? 'https'));
            $host = strtolower((string) ($base['host'] ?? self::SOURCE_HOST));
            if ($scheme !== 'https' || $host !== self::SOURCE_HOST) {
                return null;
            }
            $url = $scheme.'://'.$host.'/'.ltrim($href, '/');
        }

        $parts = parse_url($url);
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== self::SOURCE_HOST) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        if (preg_match('~^/freelances/missions/[^/]+/[^/]+/?$~', $path) !== 1) {
            return null;
        }

        return 'https://'.self::SOURCE_HOST.$path;
    }

    private function externalId(string $text, string $url): string
    {
        if (preg_match('/\bRef\s*:\s*(T\s*-?\s*[0-9]+)\b/iu', $text, $matches) === 1) {
            return strtoupper((string) preg_replace('/\s+/', '', $matches[1]));
        }

        return 'mission-'.substr(hash('sha256', $url), 0, 32);
    }

    private function heading(\DOMXPath $xpath, \DOMElement $context): string
    {
        $nodes = $xpath->query('.//*[self::h1 or self::h2 or self::h3 or self::h4]', $context);
        if (!$nodes instanceof \DOMNodeList || $nodes->length === 0) {
            return '';
        }

        return $this->clean($nodes->item(0)?->textContent ?? '');
    }

    private function firstHeading(\DOMXPath $xpath): string
    {
        foreach (['//h1', '//main//h2', '//h2'] as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes instanceof \DOMNodeList || $nodes->length === 0) {
                continue;
            }
            $value = $this->clean($nodes->item(0)?->textContent ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function publishedAt(string $text): ?string
    {
        if (preg_match('/Publi[ée]e?\s+le\s*:?[ ]*(\d{1,2})[ ]+([[:alpha:]éèêëàâäîïôöùûüç.]+)[ ]+(\d{4})/iu', $text, $matches) !== 1) {
            return null;
        }

        $month = $this->normalize((string) $matches[2]);
        $month = rtrim($month, '.');
        $months = [
            'janv' => 1, 'janvier' => 1,
            'fevr' => 2, 'fevrier' => 2,
            'mars' => 3,
            'avr' => 4, 'avril' => 4,
            'mai' => 5,
            'juin' => 6,
            'juil' => 7, 'juillet' => 7,
            'aout' => 8,
            'sept' => 9, 'septembre' => 9,
            'oct' => 10, 'octobre' => 10,
            'nov' => 11, 'novembre' => 11,
            'dec' => 12, 'decembre' => 12,
        ];
        $monthNumber = $months[$month] ?? null;
        if ($monthNumber === null) {
            return null;
        }

        try {
            return new \DateTimeImmutable(
                sprintf('%04d-%02d-%02d 00:00:00', (int) $matches[3], $monthNumber, (int) $matches[1]),
                new \DateTimeZone('Europe/Paris'),
            )->format(DATE_ATOM);
        } catch (\Exception) {
            return null;
        }
    }

    private function tjm(string $text): ?int
    {
        if (preg_match('/\bTJM\b\s*:?\s*([0-9][0-9 ]*)\s*€/iu', $text, $matches) !== 1) {
            return null;
        }

        return (int) str_replace(' ', '', $matches[1]);
    }

    private function workMode(string $text): string
    {
        if (preg_match('/pas\s+de\s+t[ée]l[ée]travail/iu', $text) === 1) {
            return 'Sur site';
        }
        if (preg_match('/t[ée]l[ée]travail\s+complet|100\s*%\s+t[ée]l[ée]travail/iu', $text) === 1) {
            return 'Télétravail';
        }
        if (preg_match('/\b[1-5]\s*j(?:our)?s?\s*\/\s*semaine\b/iu', $text) === 1) {
            return 'Hybride';
        }

        return '';
    }

    private function beforeMarker(string $text, string $marker): string
    {
        $position = mb_stripos($text, $marker);

        return $position === false ? '' : trim(mb_substr($text, 0, $position));
    }

    private function between(string $text, string $start, string $end): string
    {
        return $this->betweenAny($text, [$start], [$end]);
    }

    /** @param list<string> $starts @param list<string> $ends */
    private function betweenAny(string $text, array $starts, array $ends): string
    {
        foreach ($starts as $start) {
            $position = mb_stripos($text, $start);
            if ($position === false) {
                continue;
            }
            $value = mb_substr($text, $position + mb_strlen($start));
            $value = ltrim($value, " \t\n\r\0\x0B:");
            $endPosition = false;
            foreach ($ends as $end) {
                $candidate = mb_stripos($value, $end);
                if ($candidate !== false && ($endPosition === false || $candidate < $endPosition)) {
                    $endPosition = $candidate;
                }
            }
            if ($endPosition !== false) {
                $value = mb_substr($value, 0, $endPosition);
            }

            return $this->clean($value);
        }

        return '';
    }

    /** @param list<string> $markers */
    private function afterAny(string $text, array $markers): string
    {
        foreach ($markers as $marker) {
            $position = mb_stripos($text, $marker);
            if ($position === false) {
                continue;
            }

            return $this->clean(ltrim(mb_substr($text, $position + mb_strlen($marker)), " \t\n\r\0\x0B:"));
        }

        return '';
    }

    /** @param list<string> $starts @param list<string> $ends */
    private function section(string $text, array $starts, array $ends): string
    {
        foreach ($starts as $start) {
            $position = mb_stripos($text, $start);
            if ($position === false) {
                continue;
            }
            $value = trim(mb_substr($text, $position + mb_strlen($start)));
            $endPosition = false;
            foreach ($ends as $end) {
                $candidate = mb_stripos($value, $end);
                if ($candidate !== false && ($endPosition === false || $candidate < $endPosition)) {
                    $endPosition = $candidate;
                }
            }
            if ($endPosition !== false) {
                $value = trim(mb_substr($value, 0, $endPosition));
            }

            return $this->clean($value);
        }

        return '';
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
