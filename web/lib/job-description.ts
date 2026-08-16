const NAMED_HTML_ENTITIES: Record<string, string> = {
  amp: '&',
  apos: "'",
  bull: '•',
  copy: '©',
  euro: '€',
  gt: '>',
  hellip: '…',
  laquo: '«',
  ldquo: '“',
  lsquo: '‘',
  lt: '<',
  mdash: '—',
  middot: '·',
  nbsp: ' ',
  ndash: '–',
  quot: '"',
  raquo: '»',
  rdquo: '”',
  reg: '®',
  rsquo: '’',
  trade: '™',
};

function decodeHtmlEntities(value: string): string {
  return value.replace(/&(#x?[0-9a-f]+|[a-z][a-z0-9]+);/gi, (match, entity: string) => {
    if (entity.startsWith('#')) {
      const hexadecimal = entity[1]?.toLowerCase() === 'x';
      const rawCodePoint = hexadecimal ? entity.slice(2) : entity.slice(1);
      const codePoint = Number.parseInt(rawCodePoint, hexadecimal ? 16 : 10);

      if (Number.isInteger(codePoint) && codePoint >= 0 && codePoint <= 0x10ffff) {
        try {
          return String.fromCodePoint(codePoint);
        } catch {
          return match;
        }
      }

      return match;
    }

    return NAMED_HTML_ENTITIES[entity.toLowerCase()] ?? match;
  });
}

/**
 * Converts untrusted job-board HTML into readable plain text.
 *
 * We deliberately do not render the HTML itself: descriptions come from external
 * sources and must never become an XSS surface in the Review Queue. Structural
 * tags are translated to line breaks/bullets before all remaining markup is
 * stripped, while common and numeric HTML entities are decoded.
 */
export function jobDescriptionToPlainText(value: string | null | undefined): string {
  const input = value?.trim();
  if (!input) return '';

  const decoded = decodeHtmlEntities(input).replace(/\u00a0/g, ' ');
  const withoutNonContent = decoded
    .replace(/<!--([\s\S]*?)-->/g, ' ')
    .replace(/<(script|style|noscript)\b[^>]*>[\s\S]*?<\/\1\s*>/gi, ' ');

  const structured = withoutNonContent
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<li\b[^>]*>/gi, '• ')
    .replace(/<\/li\s*>/gi, '\n')
    .replace(/<\/(?:p|div|section|article|header|footer|h[1-6]|blockquote|pre|tr|ul|ol|table)\s*>/gi, '\n')
    .replace(/<(?:p|div|section|article|header|footer|h[1-6]|blockquote|pre|tr|ul|ol|table)\b[^>]*>/gi, '');

  return structured
    .replace(/<[^>]*>/g, ' ')
    .replace(/\r\n?/g, '\n')
    .replace(/[ \t]+\n/g, '\n')
    .replace(/\n[ \t]+/g, '\n')
    .replace(/[ \t]{2,}/g, ' ')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}
