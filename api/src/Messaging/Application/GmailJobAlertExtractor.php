<?php

declare(strict_types=1);

namespace App\Messaging\Application;

final class GmailJobAlertExtractor
{
    private AssistedJobPlatformCatalog $platforms;

    public function __construct(?AssistedJobPlatformCatalog $platforms = null)
    {
        $this->platforms = $platforms ?? new AssistedJobPlatformCatalog();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function extract(
        string $gmailMessageId,
        string $category,
        string $subject,
        string $sender,
        string $plainBody,
        string $htmlBody,
        \DateTimeImmutable $receivedAt,
    ): array {
        if (!in_array($category, ['JOB_ALERT', 'RECRUITER_OPPORTUNITY'], true)) {
            return [];
        }

        $links = $this->links($plainBody, $htmlBody);
        $offers = [];
        $seen = [];
        $description = trim($plainBody) !== '' ? trim($plainBody) : trim(strip_tags($htmlBody));
        $description = mb_substr($description, 0, 8_000);
        $senderEmail = $this->emailFromHeader($sender);

        foreach ($links as $link) {
            $url = $this->normalizeUrl($link['url']);
            if ($url === null || isset($seen[$url])) {
                continue;
            }

            $source = $this->platforms->forUrl($url);
            if ($source === null && $category !== 'RECRUITER_OPPORTUNITY') {
                continue;
            }
            if ($source === null && !$this->looksLikeJobUrl($url)) {
                continue;
            }

            $title = $this->cleanTitle($link['label']);
            if ($title === null) {
                if (count($links) !== 1) {
                    continue;
                }
                $title = $this->fallbackTitle($subject);
            }
            if ($title === null) {
                continue;
            }

            $seen[$url] = true;
            $platform = $source['name'] ?? 'Site recruteur';
            $company = $this->companyFromTitle($title) ?? $platform;
            $title = $this->titleWithoutCompany($title);

            $payload = [
                'externalId' => 'gmail-'.sha1($url),
                'sourceUrl' => $url,
                'title' => $title,
                'company' => $company,
                'location' => '',
                'contractType' => '',
                'workMode' => '',
                'description' => $description !== '' ? $description : $subject,
                'publishedAt' => $receivedAt->format(DATE_ATOM),
                'rawData' => [
                    'gmailMessageId' => $gmailMessageId,
                    'gmailCategory' => $category,
                    'alertPlatform' => $platform,
                    'alertPlatformCode' => $source['code'] ?? null,
                    'sender' => $sender,
                    'anchorLabel' => $link['label'],
                ],
            ];

            if ($category === 'RECRUITER_OPPORTUNITY' && $senderEmail !== null) {
                $payload['applicationEmail'] = $senderEmail;
            }

            $offers[] = $payload;
        }

        return $offers;
    }

    /** @return array{code: string, name: string}|null */
    public function platformForText(string $text): ?array
    {
        return $this->platforms->forText($text);
    }

    /**
     * @return list<array{url: string, label: string}>
     */
    private function links(string $plainBody, string $htmlBody): array
    {
        $links = [];

        if (trim($htmlBody) !== '') {
            $previous = libxml_use_internal_errors(true);
            $document = new \DOMDocument();
            $document->loadHTML('<?xml encoding="UTF-8">'.$htmlBody, LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($document->getElementsByTagName('a') as $anchor) {
                $href = trim($anchor->getAttribute('href'));
                if ($href === '') {
                    continue;
                }
                $links[] = [
                    'url' => $href,
                    'label' => trim(preg_replace('/\s+/u', ' ', $anchor->textContent) ?? $anchor->textContent),
                ];
            }
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (preg_match_all('~https?://[^\s<>"\']+~iu', $plainBody, $matches) === 1 || !empty($matches[0])) {
            foreach ($matches[0] ?? [] as $url) {
                $links[] = ['url' => rtrim($url, '.,;)\]'), 'label' => ''];
            }
        }

        return $links;
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return null;
        }

        for ($i = 0; $i < 2; ++$i) {
            $parts = parse_url($url);
            if (!is_array($parts)) {
                return null;
            }
            parse_str((string) ($parts['query'] ?? ''), $query);
            $redirect = null;
            foreach (['url', 'u', 'target', 'redirect', 'redirect_url', 'dest', 'destination'] as $key) {
                if (isset($query[$key]) && is_string($query[$key]) && str_starts_with(urldecode($query[$key]), 'http')) {
                    $redirect = urldecode($query[$key]);
                    break;
                }
            }
            if ($redirect === null) {
                break;
            }
            $url = $redirect;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $path = (string) ($parts['path'] ?? '/');
        parse_str((string) ($parts['query'] ?? ''), $query);
        foreach (array_keys($query) as $key) {
            if (preg_match('/^(utm_|trk|tracking|ref|source|campaign|mc_)/i', (string) $key) === 1) {
                unset($query[$key]);
            }
        }
        ksort($query);

        return $scheme.'://'.$host.$path.($query !== [] ? '?'.http_build_query($query) : '');
    }

    private function looksLikeJobUrl(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        if (preg_match('~/(unsubscribe|privacy|preferences|login|signin|account)(/|$)~', $path) === 1) {
            return false;
        }

        return preg_match('~/(job|jobs|emploi|emplois|offre|offres|mission|missions|career|careers)(/|-)~', $path) === 1;
    }

    private function cleanTitle(string $label): ?string
    {
        $label = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $label);
        if ($label === '' || mb_strlen($label) < 6 || mb_strlen($label) > 220) {
            return null;
        }

        $generic = mb_strtolower($label);
        foreach (['voir l offre', 'voir l’offre', 'voir le poste', 'postuler', 'apply now', 'view job', 'en savoir plus', 'learn more'] as $value) {
            if ($generic === $value) {
                return null;
            }
        }

        return $label;
    }

    private function fallbackTitle(string $subject): ?string
    {
        $subject = preg_replace('/^(re|fwd|fw)\s*:\s*/i', '', trim($subject)) ?? trim($subject);
        $subject = preg_replace('/^(alerte emploi|job alert|nouvelle mission|opportunit[eé])\s*[:\-–]\s*/iu', '', $subject) ?? $subject;

        return mb_strlen($subject) >= 6 && mb_strlen($subject) <= 220 ? $subject : null;
    }

    private function companyFromTitle(string $title): ?string
    {
        if (preg_match('/\s+(?:chez|at)\s+([^|–—-]{2,100})$/iu', $title, $matches) === 1) {
            return trim($matches[1]);
        }

        if (preg_match('/\s+[–—|-]\s+([^–—|]{2,100})$/u', $title, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    private function titleWithoutCompany(string $title): string
    {
        $title = preg_replace('/\s+(?:chez|at)\s+[^|–—-]{2,100}$/iu', '', $title) ?? $title;
        $title = preg_replace('/\s+[–—|]\s+[^–—|]{2,100}$/u', '', $title) ?? $title;

        return trim($title);
    }

    private function emailFromHeader(string $sender): ?string
    {
        if (preg_match('/<([^>]+)>/', $sender, $matches) === 1) {
            $sender = trim($matches[1]);
        }
        if (filter_var($sender, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return mb_strtolower($sender);
    }
}
