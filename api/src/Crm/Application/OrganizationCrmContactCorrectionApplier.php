<?php

declare(strict_types=1);

namespace App\Crm\Application;

use App\Entity\CrmContactCorrection;

final class OrganizationCrmContactCorrectionApplier
{
    /**
     * @param array<string, mixed> $directory
     * @param iterable<CrmContactCorrection> $corrections
     *
     * @return array<string, mixed>
     */
    public function apply(array $directory, iterable $corrections): array
    {
        /** @var array<string, array<string, mixed>> $correctionsByContact */
        $correctionsByContact = [];
        foreach ($corrections as $correction) {
            if (!$correction instanceof CrmContactCorrection || $correction->isEmpty()) {
                continue;
            }

            $correctionsByContact[$this->mapKey(
                $correction->getOrganizationKey(),
                $correction->getContactKey(),
            )] = $correction->toArray();
        }

        $appliedCount = 0;
        foreach ($directory['organizations'] as &$organization) {
            $organizationKey = (string) ($organization['key'] ?? '');
            if (!is_array($organization['contacts'] ?? null)) {
                continue;
            }

            foreach ($organization['contacts'] as &$contact) {
                $contactKey = (string) ($contact['key'] ?? '');
                $sourceName = is_string($contact['name'] ?? null) ? $contact['name'] : null;
                $sourceEmail = is_string($contact['email'] ?? null) ? $contact['email'] : null;
                $sourcePhone = is_string($contact['phone'] ?? null) ? $contact['phone'] : null;

                $contact['sourceName'] = $sourceName;
                $contact['sourceEmail'] = $sourceEmail;
                $contact['sourcePhone'] = $sourcePhone;
                $contact['correction'] = null;

                $correction = $correctionsByContact[$this->mapKey($organizationKey, $contactKey)] ?? null;
                if (!is_array($correction)) {
                    continue;
                }

                $correctedName = $this->optionalString($correction['correctedName'] ?? null);
                $correctedEmail = $this->optionalString($correction['correctedEmail'] ?? null);
                $correctedPhone = $this->optionalString($correction['correctedPhone'] ?? null);

                $contact['name'] = $correctedName ?? $sourceName;
                $contact['email'] = $correctedEmail ?? $sourceEmail;
                $contact['phone'] = $correctedPhone ?? $sourcePhone;
                $contact['correction'] = [
                    'correctedName' => $correctedName,
                    'correctedEmail' => $correctedEmail,
                    'correctedPhone' => $correctedPhone,
                    'updatedAt' => $correction['updatedAt'] ?? null,
                ];
                ++$appliedCount;
            }
            unset($contact);
        }
        unset($organization);

        $directory['contactCorrectionCount'] = $appliedCount;

        return $directory;
    }

    private function mapKey(string $organizationKey, string $contactKey): string
    {
        return $organizationKey."\0".$contactKey;
    }

    private function optionalString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
