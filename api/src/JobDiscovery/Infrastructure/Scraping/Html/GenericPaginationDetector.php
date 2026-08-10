<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Html;

final class GenericPaginationDetector
{
    /** @return array{nextUrl: string|null, strategy: string|null, confidence: string|null} */
    public function detect(string $html, string $currentUrl): array
    {
        $document = $this->document($html);
        $xpath = new \DOMXPath($document);

        $relNext = $xpath->query('//a[@href and contains(concat(" ", translate(normalize-space(@rel), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), " "), " next ")]');
        if ($relNext instanceof \DOMNodeList) {
            foreach ($relNext as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $url = $this->safeNextUrl($node->getAttribute('href'), $currentUrl);
                if ($url !== null) {
                    return [
                        'nextUrl' => $url,
                        'strategy' => 'REL_NEXT',
                        'confidence' => 'HIGH',
                    ];
                }
            }
        }

        $links = $xpath->query('//a[@href]');
        if ($links instanceof \DOMNodeList) {
            foreach ($links as $node) {
                if (!$node instanceof \DOMElement || !$this->looksLikeNextLink($node)) {
                    continue;
                }
                $url = $this->safeNextUrl($node->getAttribute('href'), $currentUrl);
                if ($url !== null) {
                    return [
                        'nextUrl' => $url,
                        'strategy' => 'NEXT_LABEL',
                        'confidence' => 'MEDIUM',
                    ];
                }
            }
        }

        return [
            'nextUrl' => null,
            'strategy' => null,
            'confidence' => null,
        ];
    }

    private function looksLikeNextLink(\DOMElement $link): bool
    {
        $text = $this->clean($link->textContent ?? '');
        $aria = $this->clean($link->getAttribute('aria-label'));
        $title = $this->clean($link->getAttribute('title'));
        $label = trim(implode(' ', array_filter([$text, $aria, $title])));

        if ($label === '') {
            return false;
        }

        if (preg_match('/\b(suivant|suivante|next|page\s+suivante|prochaine\s+page|résultats\s+suivants|resultats\s+suivants)\b/iu', $label) === 1) {
            return true;
        }

        return in_array(trim($text), ['›', '»', '→', '>'], true)
            && $this->insidePaginationContainer($link);
    }

    private function insidePaginationContainer(\DOMElement $link): bool
    {
        $node = $link->parentNode;
        for ($depth = 0; $depth < 4 && $node instanceof \DOMElement; ++$depth, $node = $node->parentNode) {
            $class = strtolower($node->getAttribute('class'));
            $aria = strtolower($node->getAttribute('aria-label'));
            $role = strtolower($node->getAttribute('role'));
            $tag = strtolower($node->tagName);
            if (str_contains($class, 'pagination')
                || str_contains($class, 'pager')
                || str_contains($aria, 'pagination')
                || str_contains($aria, 'pages')
                || ($tag === 'nav' && ($role === '' || $role === 'navigation'))) {
                return true;
            }
        }

        return false;
    }

    private function safeNextUrl(string $href, string $currentUrl): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '' || str_starts_with($href, '#') || preg_match('~^(?:javascript|mailto|tel):~i', $href) === 1) {
            return null;
        }

        $base = parse_url($currentUrl);
        $scheme = strtolower((string) ($base['scheme'] ?? ''));
        $host = strtolower((string) ($base['host'] ?? ''));
        if ($scheme !== 'https' || $host === '') {
            return null;
        }

        if (str_starts_with($href, '//')) {
            $url = 'https:'.$href;
        } elseif (preg_match('~^https?://~i', $href) === 1) {
            $url = $href;
        } elseif (str_starts_with($href, '?')) {
            $path = (string) ($base['path'] ?? '/');
            $url = 'https://'.$host.$path.$href;
        } elseif (str_starts_with($href, '/')) {
            $url = 'https://'.$host.$href;
        } else {
            $basePath = (string) ($base['path'] ?? '/');
            $directory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath).'/';
            $url = 'https://'.$host.$directory.$href;
        }

        $parts = parse_url($url);
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== $host
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        $path = $this->normalizePath((string) ($parts['path'] ?? '/'));
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $normalized = 'https://'.$host.$path.$query;
        $current = 'https://'.$host.$this->normalizePath((string) ($base['path'] ?? '/'))
            .(isset($base['query']) && $base['query'] !== '' ? '?'.$base['query'] : '');

        return $normalized === $current ? null : $normalized;
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
            throw new \RuntimeException('La page HTML ne peut pas être analysée pour sa pagination.');
        }

        return $document;
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
