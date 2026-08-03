<?php

declare(strict_types=1);

namespace App\Service;

final class ApplicationEmailExtractor
{
    public function extract(string $text): ?string
    {
        $decoded = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $decoded, $matches);
        $emails = array_values(array_unique(array_map('mb_strtolower', $matches[0] ?? [])));

        foreach ($emails as $email) {
            if (!$this->isUsable($email)) {
                continue;
            }

            $position = mb_stripos($decoded, $email);
            $start = $position === false ? 0 : max(0, $position - 140);
            $context = mb_substr($decoded, $start, 280);

            if (preg_match('/candidature|postul|recrut|recruit|apply|career|jobs?|talent|ressources humaines|\brh\b/iu', $context) === 1) {
                return $email;
            }
        }

        foreach ($emails as $email) {
            if (!$this->isUsable($email)) {
                continue;
            }

            $localPart = strstr($email, '@', true) ?: '';
            if (preg_match('/jobs?|career|recrut|recruit|talent|hiring|emploi|candidature|^rh$/iu', $localPart) === 1) {
                return $email;
            }
        }

        return null;
    }

    private function isUsable(string $email): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        return preg_match('/(^|[._-])(no-?reply|noreply|privacy|support|contact)([._-]|@)/iu', $email) !== 1;
    }
}
