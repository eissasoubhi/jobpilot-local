<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\ReusableAnswer;

final class ReusableAnswerResolver
{
    /** @return array<string, mixed> */
    public function resolve(ReusableAnswer $answer, CandidateProfile $profile): array
    {
        $resolvedFr = $answer->getAnswerFr();
        $resolvedEn = $answer->getAnswerEn();

        if ($answer->getValueSource() === ReusableAnswer::SOURCE_PROFILE) {
            $value = $this->readPath($profile->toAutofillArray(), $answer->getProfilePath());
            $resolvedFr = $this->stringify($value);
            $resolvedEn = $this->stringify($value);
        }

        return [
            ...$answer->toArray(),
            'resolved' => [
                'fr' => $resolvedFr,
                'en' => $resolvedEn,
            ],
            'eligibleForAutomaticFill' => $answer->isEnabled()
                && $answer->isAutoFillAllowed()
                && ($resolvedFr !== null || $resolvedEn !== null),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function readPath(array $payload, ?string $path): mixed
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $value = $payload;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }
}
