<?php

declare(strict_types=1);

namespace App\Messaging\Infrastructure\Gmail;

final class GmailMessageDecoder
{
    /**
     * @param array<string, mixed> $message
     * @return array{
     *     gmailMessageId: string,
     *     threadId: string,
     *     sender: string,
     *     recipient: string,
     *     replyTo: string|null,
     *     subject: string,
     *     plainBody: string,
     *     htmlBody: string,
     *     snippet: string,
     *     receivedAt: \DateTimeImmutable
     * }
     */
    public function decode(array $message): array
    {
        $payload = is_array($message['payload'] ?? null) ? $message['payload'] : [];
        $headers = $this->headers($payload);
        $bodies = ['plain' => [], 'html' => []];
        $this->collectBodies($payload, $bodies);

        $plainBody = trim(implode("\n\n", array_filter($bodies['plain'])));
        $htmlBody = trim(implode("\n", array_filter($bodies['html'])));
        if ($plainBody === '' && $htmlBody !== '') {
            $plainBody = $this->htmlToText($htmlBody);
        }

        $receivedAt = new \DateTimeImmutable();
        if (isset($message['internalDate']) && is_numeric($message['internalDate'])) {
            $receivedAt = $receivedAt->setTimestamp((int) floor(((int) $message['internalDate']) / 1000));
        }

        return [
            'gmailMessageId' => trim((string) ($message['id'] ?? '')),
            'threadId' => trim((string) ($message['threadId'] ?? '')),
            'sender' => trim((string) ($headers['from'] ?? '')),
            'recipient' => trim((string) ($headers['to'] ?? '')),
            'replyTo' => isset($headers['reply-to']) && trim($headers['reply-to']) !== ''
                ? trim($headers['reply-to'])
                : null,
            'subject' => $this->decodeHeader((string) ($headers['subject'] ?? '')),
            'plainBody' => mb_substr($plainBody, 0, 100_000),
            'htmlBody' => mb_substr($htmlBody, 0, 200_000),
            'snippet' => mb_substr(trim((string) ($message['snippet'] ?? '')), 0, 4_000),
            'receivedAt' => $receivedAt,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function headers(array $payload): array
    {
        $result = [];
        foreach ($payload['headers'] ?? [] as $header) {
            if (!is_array($header)) {
                continue;
            }

            $name = mb_strtolower(trim((string) ($header['name'] ?? '')));
            if ($name === '') {
                continue;
            }
            $result[$name] = $this->decodeHeader((string) ($header['value'] ?? ''));
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $part
     * @param array{plain: list<string>, html: list<string>} $bodies
     */
    private function collectBodies(array $part, array &$bodies): void
    {
        $mimeType = mb_strtolower(trim((string) ($part['mimeType'] ?? '')));
        $body = is_array($part['body'] ?? null) ? $part['body'] : [];
        $data = trim((string) ($body['data'] ?? ''));

        if ($data !== '') {
            $decoded = $this->decodeBase64Url($data);
            if ($decoded !== '') {
                if ($mimeType === 'text/plain') {
                    $bodies['plain'][] = $decoded;
                } elseif ($mimeType === 'text/html') {
                    $bodies['html'][] = $decoded;
                }
            }
        }

        foreach ($part['parts'] ?? [] as $child) {
            if (is_array($child)) {
                $this->collectBodies($child, $bodies);
            }
        }
    }

    private function decodeBase64Url(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
        $decoded = base64_decode($value, true);

        return is_string($decoded) ? $decoded : '';
    }

    private function decodeHeader(string $value): string
    {
        if (function_exists('mb_decode_mimeheader')) {
            $decoded = mb_decode_mimeheader($value);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $value;
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|div|li|tr|h[1-6])>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
