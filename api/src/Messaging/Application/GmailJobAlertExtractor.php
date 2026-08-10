<?php

declare(strict_types=1);

namespace App\Messaging\Application;

use App\JobDiscovery\Application\JobTextMetadataExtractor;
use App\Service\JobDescriptionContaminationDetector;

final class GmailJobAlertExtractor
{
    private const MAX_DESCRIPTION_LENGTH = 8_000;
    private const MAX_LINK_CONTEXT_LENGTH = 2_500;

    private AssistedJobPlatformCatalog $platforms;
    private PlainTextJobAlertLinkExtractor $plainTextLinks;
    private JobTextMetadataExtractor $textMetadata;
    private JobDescriptionContaminationDetector $descriptionContamination;

    public function __construct(
        ?AssistedJobPlatformCatalog $platforms = null,
        ?PlainTextJobAlertLinkExtractor $plainTextLinks = null,
        ?JobTextMetadataExtractor $textMetadata = null,
        ?JobDescriptionContaminationDetector $descriptionContamination = null,
    ) {
        $this->platforms = $platforms ?? new AssistedJobPlatformCatalog();
        $this->plainTextLinks = $plainTextLinks ?? new PlainTextJobAlertLinkExtractor();
        $this->textMetadata = $textMetadata ?? new JobTextMetadataExtractor();
        $this->descriptionContamination = $descriptionContamination ?? new JobDescriptionContaminationDetector();
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

        $preparedLinks = $this->prepareEligibleLinks($this->links($plainBody, $htmlBody), $category);
        $eligibleLinkCount = count($preparedLinks);
        $offers = [];
        $seen = [];
        $fullMessageDescription = $this->cleanText(trim($plainBody) !== '' ? $plainBody : strip_tags($htmlBody));
        $messageDigestDetected = $this->descriptionContamination->isMultiOfferDigest($fullMessageDescription);
        $globalDescription = mb_substr($fullMessageDescription, 0, self::MAX_DESCRIPTION_LENGTH);
        $senderEmail = $this->emailFromHeader($sender);

        foreach ($preparedLinks as $prepared) {
            $url = $prepared['url'];
            if (isset($seen[$url])) {
                continue;
            }

            $link = $prepared['link'];
            $source = $prepared['source'];
            $title = $this->cleanTitle($link['label']);
            if ($title === null && $eligibleLinkCount === 1 && !$messageDigestDetected) {
                $title = $this->fallbackTitle($subject);
            }
            if ($title === null) {
                continue;
            }

            $seen[$url] = true;
            $platform = $source['name'] ?? 'Site recruteur';
            $company = $this->companyFromTitle($title) ?? $platform;
            $title = $this->titleWithoutCompany($title);
            [$description, $descriptionScope] = $this->descriptionForLink(
                $link,
                $title,
                $subject,
                $globalDescription,
                $eligibleLinkCount,
                $messageDigestDetected,
            );
            $metadata = $this->textMetadata->extract($description);

            $payload = [
                'externalId' => 'gmail-'.sha1($url),
                'sourceUrl' => $url,
                'title' => $title,
                'company' => $company,
                'location' => '',
                'contractType' => $metadata['contractType'],
                'workMode' => $metadata['workMode'],
                'description' => $description,
                'publishedAt' => $receivedAt->format(DATE_ATOM),
                'tjmMin' => $metadata['tjmMin'],
                'tjmMax' => $metadata['tjmMax'],
                'rawData' => [
                    'gmailMessageId' => $gmailMessageId,
                    'gmailCategory' => $category,
                    'alertPlatform' => $platform,
                    'alertPlatformCode' => $source['code'] ?? null,
                    'sender' => $sender,
                    'anchorLabel' => $link['label'],
                    'descriptionScope' => $descriptionScope,
                    'eligibleLinkCount' => $eligibleLinkCount,
                    'messageDigestDetected' => $messageDigestDetected,
                    'textMetadataExtracted' => true,
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
     * @param list<array{url: string, label: string, context: string}> $links
     * @return list<array{url: string, link: array{url: string, label: string, context: string}, source: array{code: string, name: string}|null}>
     */
    private function prepareEligibleLinks(array $links, string $category): array
    {
        $prepared = [];
        $seen = [];

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

            $seen[$url] = true;
            $prepared[] = [
                'url' => $url,
                'link' => $link,
                'source' => $source,
            ];
        }

        return $prepared;
    }

    /**
     * @return list<array{url: string, label: string, context: string}>
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
                    'label' => $this->cleanText($anchor->textContent),
                    'context' => $this->htmlLinkContext($anchor),
                ];
            }
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        foreach ($this->plainTextLinks->extract($plainBody) as $link) {
            $links[] = $link;
        }

        return $links;
    }

    private function htmlLinkContext(\DOMElement $anchor): string
    {
        $node = $anchor->parentNode;
        $depth = 0;
        while ($node instanceof \DOMElement && $depth < 6) {
            $tag = mb_strtolower($node->tagName);
            if (in_array($tag, ['li', 'tr', 'td', 'article', 'section', 'div', 'p'], true)) {
                $text = $this->cleanText($node->textContent);
                $linkCount = $node->getElementsByTagName('a')->length;
                if (mb_strlen($text) >= 20 && mb_strlen($text) <= self::MAX_LINK_CONTEXT_LENGTH && $linkCount <= 2) {
                    return $text;
                }
            }
            $node = $node->parentNode;
            ++$depth;
        }

        return $this->cleanText($anchor->textContent);
    }

    /**
     * @param array{url: string, label: string, context: string} $link
     * @return array{string, string}
     */
    private function descriptionForLink(
        array $link,
        string $title,
        string $subject,
        string $globalDescription,
        int $eligibleLinkCount,
        bool $messageDigestDetected,
    ): array {
        $context = $this->cleanAlertDescription($link['context']);
        $label = $this->cleanText($link['label']);
        $minimumLocalLength = max(30, mb_strlen($label) + 12);
        if ($context !== '' && mb_strlen($context) >= $minimumLocalLength) {
            return [mb_substr($context, 0, self::MAX_DESCRIPTION_LENGTH), 'LINK_CONTEXT'];
        }

        if (!$messageDigestDetected && $eligibleLinkCount === 1 && $globalDescription !== '') {
            $messageBody = $this->cleanAlertDescription($globalDescription);
            if ($messageBody !== '') {
                return [mb_substr($messageBody, 0, self::MAX_DESCRIPTION_LENGTH), 'MESSAGE_BODY'];
            }
        }

        if ($messageDigestDetected) {
            return [mb_substr($title, 0, self::MAX_DESCRIPTION_LENGTH), 'TITLE_ONLY_DIGEST'];
        }

        $fallback = $this->cleanText($title.' — '.$subject);

        return [mb_substr($fallback !== '' ? $fallback : $title, 0, self::MAX_DESCRIPTION_LENGTH), 'TITLE_SUBJECT'];
    }

    private function cleanAlertDescription(string $value): string
    {
        $value = $this->cleanText($value);
        $value = preg_replace('~https?://[^\s<>"\']+~iu', ' ', $value) ?? $value;
        $value = preg_replace('/\bvoir\s+l[’\']?offre(?:\s+d[’\']emploi)?\s*:?\s*/iu', ' ', $value) ?? $value;
        $value = preg_replace('/(?:[-–—_*·.]\s*){6,}/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
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
        $label = $this->cleanText($label);
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

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
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
