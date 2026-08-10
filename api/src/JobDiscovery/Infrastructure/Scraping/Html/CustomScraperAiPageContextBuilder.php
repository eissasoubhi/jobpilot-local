<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Html;

final class CustomScraperAiPageContextBuilder
{
    private const MAX_TEXT_CHARACTERS = 20_000;
    private const MAX_ANCHORS = 120;

    /** @return array{visibleText: string, anchors: list<array{url: string, text: string}>} */
    public function build(string $html, string $pageUrl): array
    {
        $document = $this->document($html);
        $xpath = new \DOMXPath($document);

        return [
            'visibleText' => mb_substr($this->visibleText($document), 0, self::MAX_TEXT_CHARACTERS),
            'anchors' => $this->anchors($xpath, $pageUrl),
        ];
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
            throw new \RuntimeException('Le HTML ne peut pas être préparé pour l’interprétation IA.');
        }

        return $document;
    }

    private function visibleText(\DOMDocument $document): string
    {
        $clone = clone $document;
        $xpath = new \DOMXPath($clone);
        foreach (['//script', '//style', '//noscript', '//template', '//svg', '//header', '//footer', '//nav'] as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes instanceof \DOMNodeList) {
                continue;
            }
            for ($index = $nodes->length - 1; $index >= 0; --$index) {
                $node = $nodes->item($index);
                $node?->parentNode?->removeChild($node);
            }
        }

        $main = $xpath->query('//main')?->item(0)
            ?? $xpath->query('//body')?->item(0);

        return $this->clean($main?->textContent ?? $clone->textContent ?? '');
    }

    /** @return list<array{url: string, text: string}> */
    private function anchors(\DOMXPath $xpath, string $pageUrl): array
    {
        $nodes = $xpath->query('//a[@href]');
        if (!$nodes instanceof \DOMNodeList) {
            return [];
        }

        $anchors = [];
        $seen = [];
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $url = $this->sameDomainUrl($node->getAttribute('href'), $pageUrl);
            if ($url === null || isset($seen[$url])) {
                continue;
            }

            $text = mb_substr($this->clean($node->textContent ?? ''), 0, 240);
            if ($text === '') {
                $aria = $this->clean($node->getAttribute('aria-label'));
                $title = $this->clean($node->getAttribute('title'));
                $text = mb_substr($aria !== '' ? $aria : $title, 0, 240);
            }
            if ($text === '') {
                continue;
            }

            $seen[$url] = true;
            $anchors[] = ['url' => $url, 'text' => $text];
            if (count($anchors) >= self::MAX_ANCHORS) {
                break;
            }
        }

        return $anchors;
    }

    private function sameDomainUrl(string $href, string $pageUrl): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '' || str_starts_with($href, '#') || preg_match('~^(?:javascript|mailto|tel):~i', $href) === 1) {
            return null;
        }

        $base = parse_url($pageUrl);
        $host = strtolower((string) ($base['host'] ?? ''));
        if (strtolower((string) ($base['scheme'] ?? '')) !== 'https' || $host === '') {
            return null;
        }

        if (str_starts_with($href, '//')) {
            $url = 'https:'.$href;
        } elseif (preg_match('~^https?://~i', $href) === 1) {
            $url = $href;
        } elseif (str_starts_with($href, '?')) {
            $url = 'https://'.$host.(string) ($base['path'] ?? '/').$href;
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

        return 'https://'.$host.$path.$query;
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

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
