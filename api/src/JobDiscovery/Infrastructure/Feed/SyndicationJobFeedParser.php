<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Feed;

final class SyndicationJobFeedParser
{
    /** @return list<array<string, mixed>> */
    public function parse(string $xml, string $sourceName): array
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);
            $errors = libxml_get_errors();
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            $message = isset($errors[0]) ? trim($errors[0]->message) : 'XML invalide.';
            throw new \RuntimeException('Le flux d’offres est invalide : '.$message);
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('/*[local-name()="rss"]/*[local-name()="channel"]/*[local-name()="item"]');
        if (!$nodes instanceof \DOMNodeList || $nodes->length === 0) {
            $nodes = $xpath->query('/*[local-name()="feed"]/*[local-name()="entry"]');
        }

        if (!$nodes instanceof \DOMNodeList) {
            return [];
        }

        $offers = [];
        $seen = [];

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $titleValue = $this->text($xpath, './*[local-name()="title"]', $node);
            $url = $this->entryUrl($xpath, $node);
            $rawDescription = $this->text(
                $xpath,
                './*[local-name()="description" or local-name()="content" or local-name()="summary"]',
                $node,
            );
            $description = $this->clean($rawDescription);
            [$title, $company] = $this->splitTitleAndCompany($titleValue);
            if ($company === '') {
                $company = $this->clean($this->text($xpath, './*[local-name()="author"]/*[local-name()="name"]', $node));
            }

            if ($title === '') {
                continue;
            }

            $externalId = $this->clean($this->text($xpath, './*[local-name()="guid" or local-name()="id"]', $node));
            if ($externalId === '') {
                $externalId = 'feed-'.hash('sha256', $url.'|'.$title.'|'.$company);
            }
            if (isset($seen[$externalId])) {
                continue;
            }
            $seen[$externalId] = true;

            $publishedAt = $this->date(
                $this->text($xpath, './*[local-name()="pubDate" or local-name()="published" or local-name()="updated"]', $node),
            );
            $combined = trim($titleValue.' '.$description);
            $workMode = $this->workMode($combined);
            $compensation = $this->compensation($combined);

            $offers[] = [
                'source' => $sourceName,
                'sourceUrl' => $url !== '' ? $url : null,
                'externalId' => mb_substr($externalId, 0, 180),
                'title' => $title,
                'company' => $company,
                'location' => $this->location($combined, $workMode),
                'contractType' => $this->contractType($combined),
                'workMode' => $workMode,
                'language' => $this->language($combined),
                'description' => $description !== '' ? $description : $titleValue,
                'publishedAt' => $publishedAt,
                'salaryMin' => $compensation['salaryMin'],
                'salaryMax' => $compensation['salaryMax'],
                'tjmMin' => $compensation['tjmMin'],
                'tjmMax' => $compensation['tjmMax'],
                'rawData' => [
                    'title' => $titleValue,
                    'link' => $url,
                    'guid' => $externalId,
                    'publishedAt' => $publishedAt,
                    'description' => $description,
                ],
            ];
        }

        return $offers;
    }

    private function entryUrl(\DOMXPath $xpath, \DOMElement $entry): string
    {
        $rssLink = $this->clean($this->text($xpath, './*[local-name()="link" and not(@href)]', $entry));
        if ($rssLink !== '') {
            return $rssLink;
        }

        $links = $xpath->query('./*[local-name()="link" and @href]', $entry);
        if (!$links instanceof \DOMNodeList) {
            return '';
        }

        $fallback = '';
        foreach ($links as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }
            $href = trim($link->getAttribute('href'));
            if ($href === '') {
                continue;
            }
            $fallback = $fallback !== '' ? $fallback : $href;
            $rel = strtolower(trim($link->getAttribute('rel')));
            if ($rel === '' || $rel === 'alternate') {
                return $href;
            }
        }

        return $fallback;
    }

    private function text(\DOMXPath $xpath, string $query, \DOMNode $context): string
    {
        $nodes = $xpath->query($query, $context);
        if (!$nodes instanceof \DOMNodeList || $nodes->length === 0) {
            return '';
        }

        return trim((string) $nodes->item(0)?->textContent);
    }

    /** @return array{0: string, 1: string} */
    private function splitTitleAndCompany(string $value): array
    {
        $value = $this->clean($value);
        if (preg_match('/^(.+?)\s+at\s+(.+)$/iu', $value, $matches) === 1) {
            return [$this->clean($matches[1]), $this->clean($matches[2])];
        }
        if (preg_match('/^(.+?)\s+chez\s+(.+)$/iu', $value, $matches) === 1) {
            return [$this->clean($matches[1]), $this->clean($matches[2])];
        }

        return [$value, ''];
    }

    private function contractType(string $text): string
    {
        if (preg_match('/freelance|contract\s*\/\s*freelance|contractor|ind[ée]pendant/iu', $text) === 1) {
            return 'Freelance';
        }
        if (preg_match('/\bcdd\b|fixed[ -]?term|temporary|temporaire/iu', $text) === 1) {
            return 'CDD';
        }
        if (preg_match('/\bcdi\b|full[ -]?time|permanent|temps plein/iu', $text) === 1) {
            return 'CDI';
        }

        return '';
    }

    private function workMode(string $text): string
    {
        if (preg_match('/remote\s*\+\s*part[- ]time onsite|hybrid|hybride/iu', $text) === 1) {
            return 'Hybride';
        }
        if (preg_match('/full(?:y)? remote|100\s*%\s*(?:remote|t[ée]l[ée]travail)|\bremote\b|t[ée]l[ée]travail complet/iu', $text) === 1) {
            return 'Télétravail';
        }
        if (preg_match('/on[- ]?site|onsite|sur site|pr[ée]sentiel/iu', $text) === 1) {
            return 'Sur site';
        }

        return '';
    }

    private function location(string $text, string $workMode): string
    {
        if (preg_match('/(?:remote\s*\+\s*part[- ]time onsite|hybrid|hybride)\s*\(([^)]+)\)/iu', $text, $matches) === 1) {
            return $this->clean($matches[1]);
        }
        if (preg_match('/(?:location|lieu)\s*[:\-]\s*([^|•]{2,120})/iu', $text, $matches) === 1) {
            return $this->clean($matches[1]);
        }
        if ($workMode === 'Télétravail') {
            return 'Télétravail';
        }

        return '';
    }

    /** @return array{salaryMin: ?int, salaryMax: ?int, tjmMin: ?int, tjmMax: ?int} */
    private function compensation(string $text): array
    {
        $result = ['salaryMin' => null, 'salaryMax' => null, 'tjmMin' => null, 'tjmMax' => null];
        $pattern = '/(?:€|EUR|\$|USD|CA\$|GBP|£)\s*([0-9][0-9\s,.]*)(?:\s*[–—-]\s*(?:€|EUR|\$|USD|CA\$|GBP|£)?\s*([0-9][0-9\s,.]*))?\s*\/\s*(day|jour|year|an|annual)/iu';
        if (preg_match($pattern, $text, $matches) !== 1) {
            return $result;
        }

        $minimum = $this->amount($matches[1]);
        $maximum = isset($matches[2]) && trim($matches[2]) !== '' ? $this->amount($matches[2]) : $minimum;
        $period = mb_strtolower($matches[3]);

        if (in_array($period, ['day', 'jour'], true)) {
            $result['tjmMin'] = $minimum;
            $result['tjmMax'] = $maximum;
        } else {
            $result['salaryMin'] = $minimum;
            $result['salaryMax'] = $maximum;
        }

        return $result;
    }

    private function amount(string $value): int
    {
        $normalized = preg_replace('/[^0-9]/', '', $value) ?? '';

        return $normalized === '' ? 0 : (int) $normalized;
    }

    private function language(string $text): string
    {
        return preg_match('/\b(d[ée]veloppeur|mission|t[ée]l[ée]travail|salaire|entreprise|exp[ée]rience)\b/iu', $text) === 1
            ? 'fr'
            : 'en';
    }

    private function date(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (\Exception) {
            return null;
        }
    }

    private function clean(string $value): string
    {
        return trim(preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ) ?? '');
    }
}
