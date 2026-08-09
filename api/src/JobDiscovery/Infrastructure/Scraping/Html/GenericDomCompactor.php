<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Html;

final class GenericDomCompactor
{
    private const MAX_CHARACTERS = 60_000;
    private const MAX_STRUCTURED_DATA_CHARACTERS = 20_000;

    /**
     * @return array{
     *   content: string,
     *   originalBytes: int,
     *   compactedCharacters: int,
     *   truncated: bool,
     *   structuredDataBlocks: int
     * }
     */
    public function compact(string $html): array
    {
        $document = $this->document($html);
        $xpath = new \DOMXPath($document);
        $structuredData = $this->structuredData($xpath);

        foreach (['//script', '//style', '//noscript', '//template', '//svg', '//iframe', '//canvas'] as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes instanceof \DOMNodeList) {
                continue;
            }
            for ($index = $nodes->length - 1; $index >= 0; --$index) {
                $node = $nodes->item($index);
                $node?->parentNode?->removeChild($node);
            }
        }

        $comments = $xpath->query('//comment()');
        if ($comments instanceof \DOMNodeList) {
            for ($index = $comments->length - 1; $index >= 0; --$index) {
                $comment = $comments->item($index);
                $comment?->parentNode?->removeChild($comment);
            }
        }

        $elements = $xpath->query('//*');
        if ($elements instanceof \DOMNodeList) {
            foreach ($elements as $element) {
                if (!$element instanceof \DOMElement || !$element->hasAttributes()) {
                    continue;
                }

                $remove = [];
                foreach ($element->attributes as $attribute) {
                    $name = strtolower($attribute->name);
                    if (!in_array($name, ['href', 'datetime', 'itemprop', 'itemtype', 'class', 'id', 'role'], true)) {
                        $remove[] = $attribute->name;
                        continue;
                    }

                    $value = trim(preg_replace('/\s+/u', ' ', $attribute->value) ?? '');
                    $limit = $name === 'href' ? 1_000 : 200;
                    $element->setAttribute($attribute->name, mb_substr($value, 0, $limit));
                }

                foreach ($remove as $attributeName) {
                    $element->removeAttribute($attributeName);
                }
            }
        }

        $body = $xpath->query('//body')?->item(0);
        $bodyHtml = $body instanceof \DOMNode
            ? (string) $document->saveHTML($body)
            : (string) $document->saveHTML();
        $bodyHtml = preg_replace('/>\s+</u', '><', $bodyHtml) ?? $bodyHtml;
        $bodyHtml = preg_replace('/[\t\r\n]+/u', ' ', $bodyHtml) ?? $bodyHtml;
        $bodyHtml = preg_replace('/ {2,}/u', ' ', $bodyHtml) ?? $bodyHtml;
        $bodyHtml = trim($bodyHtml);

        $content = '';
        if ($structuredData !== []) {
            $content .= "<jobpilot-structured-data>\n".implode("\n", $structuredData)."\n</jobpilot-structured-data>\n";
        }
        $content .= $bodyHtml;

        $truncated = mb_strlen($content) > self::MAX_CHARACTERS;
        if ($truncated) {
            $content = mb_substr($content, 0, self::MAX_CHARACTERS);
        }

        return [
            'content' => $content,
            'originalBytes' => strlen($html),
            'compactedCharacters' => mb_strlen($content),
            'truncated' => $truncated,
            'structuredDataBlocks' => count($structuredData),
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
            throw new \RuntimeException('Le DOM du site ne peut pas être préparé pour Gemini.');
        }

        return $document;
    }

    /** @return list<string> */
    private function structuredData(\DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//script[contains(translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "ld+json")]');
        if (!$nodes instanceof \DOMNodeList) {
            return [];
        }

        $blocks = [];
        $characters = 0;
        foreach ($nodes as $node) {
            $value = trim((string) ($node->textContent ?? ''));
            if ($value === '' || stripos($value, 'JobPosting') === false) {
                continue;
            }

            $remaining = self::MAX_STRUCTURED_DATA_CHARACTERS - $characters;
            if ($remaining <= 0) {
                break;
            }

            $value = mb_substr($value, 0, $remaining);
            $blocks[] = $value;
            $characters += mb_strlen($value);
        }

        return $blocks;
    }
}
