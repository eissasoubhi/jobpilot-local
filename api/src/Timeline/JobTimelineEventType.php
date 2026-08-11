<?php

declare(strict_types=1);

namespace App\Timeline;

final class JobTimelineEventType
{
    public const OFFER_IMPORTED = 'OFFER_IMPORTED';
    public const SOURCE_OCCURRENCE_ADDED = 'SOURCE_OCCURRENCE_ADDED';
    public const PREPARATION_CREATED = 'PREPARATION_CREATED';
    public const PREPARATION_UPDATED = 'PREPARATION_UPDATED';
    public const APPLICATION_SUBMITTED = 'APPLICATION_SUBMITTED';
    public const RESPONSE_RECEIVED = 'RESPONSE_RECEIVED';
    public const REJECTED = 'REJECTED';
    public const INTERVIEW = 'INTERVIEW';
    public const FOLLOW_UP = 'FOLLOW_UP';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::OFFER_IMPORTED,
            self::SOURCE_OCCURRENCE_ADDED,
            self::PREPARATION_CREATED,
            self::PREPARATION_UPDATED,
            self::APPLICATION_SUBMITTED,
            self::RESPONSE_RECEIVED,
            self::REJECTED,
            self::INTERVIEW,
            self::FOLLOW_UP,
        ];
    }

    public static function assertSupported(string $type): string
    {
        $type = strtoupper(trim($type));
        if (!in_array($type, self::all(), true)) {
            throw new \InvalidArgumentException(sprintf('Type d’événement timeline non supporté : %s.', $type === '' ? '(vide)' : $type));
        }

        return $type;
    }
}
