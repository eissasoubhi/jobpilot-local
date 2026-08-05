<?php

declare(strict_types=1);

namespace App\Crm\Application;

use App\Entity\Application;
use App\Entity\InboxMessage;
use App\Entity\Positioning;

final class OrganizationCrmDirectoryBuilder
{
    /**
     * @param iterable<Application> $applications
     * @param iterable<Positioning> $positionings
     * @param iterable<InboxMessage> $messages
     *
     * @return array{
     *   generatedAt: string,
     *   organizationCount: int,
     *   contactCount: int,
     *   organizations: list<array<string, mixed>>
     * }
     */
    public function build(iterable $applications, iterable $positionings, iterable $messages): array
    {
        /** @var array<string, array<string, mixed>> $organizations */
        $organizations = [];

        foreach ($applications as $application) {
            $data = $application->toArray();
            $job = is_array($data['jobOffer'] ?? null) ? $data['jobOffer'] : [];
            $companyName = trim((string) ($job['company'] ?? ''));
            $clientName = trim((string) ($job['clientName'] ?? ''));
            $organizationKeys = [];

            if ($companyName !== '') {
                $organizationKeys[] = $this->ensureOrganization($organizations, $companyName, 'COMPANY');
            }
            if ($clientName !== '' && $this->normalizeName($clientName) !== $this->normalizeName($companyName)) {
                $organizationKeys[] = $this->ensureOrganization($organizations, $clientName, 'CLIENT');
            }

            foreach (array_unique($organizationKeys) as $organizationKey) {
                $organization = &$organizations[$organizationKey];
                $applicationKey = $this->stableRecordKey('application', $data['id'] ?? null, [
                    $job['title'] ?? '',
                    $data['createdAt'] ?? '',
                ]);

                if (!isset($organization['_applications'][$applicationKey])) {
                    $organization['_applications'][$applicationKey] = true;
                    $status = $this->safeStatus($data['status'] ?? null);
                    $organization['applicationStatuses'][$status] = ($organization['applicationStatuses'][$status] ?? 0) + 1;
                }

                $this->addOffer($organization, $job);
                $this->rememberActivity($organization, $data['updatedAt'] ?? $data['createdAt'] ?? null);
                unset($organization);
            }

            if ($companyName !== '') {
                $companyKey = $this->normalizeName($companyName);
                $applicationEmail = $this->normalizeEmail($job['applicationEmail'] ?? null);
                if ($applicationEmail !== null) {
                    $this->addContact(
                        $organizations[$companyKey],
                        null,
                        $applicationEmail,
                        null,
                        'APPLICATION_ADDRESS',
                        $data['updatedAt'] ?? null,
                        false,
                    );
                }
            }
        }

        foreach ($positionings as $positioning) {
            $data = $positioning->toArray();
            $agencyName = trim((string) ($data['agency'] ?? ''));
            $clientName = trim((string) ($data['finalClient'] ?? ''));
            $organizationKeys = [];

            if ($agencyName !== '') {
                $organizationKeys[] = $this->ensureOrganization($organizations, $agencyName, 'AGENCY');
            }
            if ($clientName !== '' && $this->normalizeName($clientName) !== $this->normalizeName($agencyName)) {
                $organizationKeys[] = $this->ensureOrganization($organizations, $clientName, 'CLIENT');
            }

            foreach (array_unique($organizationKeys) as $organizationKey) {
                $organization = &$organizations[$organizationKey];
                $positioningKey = $this->stableRecordKey('positioning', $data['id'] ?? null, [
                    $data['missionTitle'] ?? '',
                    $data['createdAt'] ?? '',
                ]);

                if (!isset($organization['_positionings'][$positioningKey])) {
                    $organization['_positionings'][$positioningKey] = true;
                    $status = $this->safeStatus($data['status'] ?? null);
                    $organization['positioningStatuses'][$status] = ($organization['positioningStatuses'][$status] ?? 0) + 1;
                }

                $this->rememberActivity($organization, $data['updatedAt'] ?? $data['createdAt'] ?? null);
                unset($organization);
            }

            if ($agencyName !== '') {
                $agencyKey = $this->normalizeName($agencyName);
                $this->addContact(
                    $organizations[$agencyKey],
                    trim((string) ($data['recruiterName'] ?? '')) ?: null,
                    $this->normalizeEmail($data['recruiterEmail'] ?? null),
                    trim((string) ($data['recruiterPhone'] ?? '')) ?: null,
                    'RECRUITER',
                    $data['updatedAt'] ?? null,
                    false,
                );
            }
        }

        foreach ($messages as $message) {
            $data = $message->toArray();
            $job = is_array($data['jobOffer'] ?? null) ? $data['jobOffer'] : [];
            $companyName = trim((string) ($job['company'] ?? ''));
            if ($companyName === '') {
                continue;
            }

            $organizationKey = $this->ensureOrganization($organizations, $companyName, 'COMPANY');
            $organization = &$organizations[$organizationKey];
            $messageKey = $this->stableRecordKey('message', $data['id'] ?? $data['gmailMessageId'] ?? null, [
                $data['subject'] ?? '',
                $data['receivedAt'] ?? '',
            ]);

            if (!isset($organization['_messages'][$messageKey])) {
                $organization['_messages'][$messageKey] = true;
            }

            [$contactName, $contactEmail] = $this->parseMailbox(
                trim((string) ($data['replyTo'] ?? '')) !== ''
                    ? (string) $data['replyTo']
                    : (string) ($data['sender'] ?? ''),
            );
            $this->addContact(
                $organization,
                $contactName,
                $contactEmail,
                null,
                'INBOX_CONTACT',
                $data['receivedAt'] ?? null,
                true,
            );
            $this->rememberActivity($organization, $data['receivedAt'] ?? null);
            unset($organization);
        }

        $result = array_map(fn (array $organization): array => $this->finalizeOrganization($organization), array_values($organizations));
        usort($result, static function (array $left, array $right): int {
            $activityComparison = strcmp((string) ($right['lastActivityAt'] ?? ''), (string) ($left['lastActivityAt'] ?? ''));

            return $activityComparison !== 0
                ? $activityComparison
                : strcasecmp((string) $left['name'], (string) $right['name']);
        });

        return [
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'organizationCount' => count($result),
            'contactCount' => array_sum(array_map(static fn (array $organization): int => (int) $organization['contactCount'], $result)),
            'organizations' => $result,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $organizations
     */
    private function ensureOrganization(array &$organizations, string $name, string $role): string
    {
        $key = $this->normalizeName($name);
        if ($key === '') {
            throw new \InvalidArgumentException('Organization name cannot be empty.');
        }

        if (!isset($organizations[$key])) {
            $organizations[$key] = [
                'key' => $key,
                'name' => trim($name),
                'roles' => [],
                'applicationStatuses' => [],
                'positioningStatuses' => [],
                'lastActivityAt' => null,
                '_applications' => [],
                '_positionings' => [],
                '_messages' => [],
                '_offers' => [],
                '_contacts' => [],
            ];
        }

        $organizations[$key]['roles'][$role] = true;

        return $key;
    }

    /** @param array<string, mixed> $organization */
    private function addOffer(array &$organization, array $job): void
    {
        $title = trim((string) ($job['title'] ?? ''));
        if ($title === '') {
            return;
        }

        $offerKey = $this->stableRecordKey('offer', $job['id'] ?? null, [
            $title,
            $job['sourceUrl'] ?? '',
        ]);
        $activityAt = $this->normalizeDate($job['publishedAt'] ?? $job['discoveredAt'] ?? null);

        $organization['_offers'][$offerKey] = [
            'id' => is_numeric($job['id'] ?? null) ? (int) $job['id'] : null,
            'title' => $title,
            'status' => $this->safeStatus($job['status'] ?? null),
            'score' => max(0, min(100, (int) ($job['score'] ?? 0))),
            'sourceUrl' => is_string($job['sourceUrl'] ?? null) && trim($job['sourceUrl']) !== '' ? trim($job['sourceUrl']) : null,
            'activityAt' => $activityAt,
        ];
        $this->rememberActivity($organization, $activityAt);
    }

    /** @param array<string, mixed> $organization */
    private function addContact(
        array &$organization,
        ?string $name,
        ?string $email,
        ?string $phone,
        string $role,
        mixed $activityAt,
        bool $message,
    ): void {
        $name = $name !== null ? trim($name, " \t\n\r\0\x0B\"") : null;
        $name = $name !== '' ? $name : null;
        $email = $this->normalizeEmail($email);
        $phone = $phone !== null && trim($phone) !== '' ? trim($phone) : null;

        if ($name === null && $email === null && $phone === null) {
            return;
        }

        $contactKey = $email ?? $this->normalizeName(($name ?? '').' '.($phone ?? ''));
        if ($contactKey === '') {
            return;
        }

        if (!isset($organization['_contacts'][$contactKey])) {
            $organization['_contacts'][$contactKey] = [
                'key' => $contactKey,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'roles' => [],
                'messageCount' => 0,
                'lastContactAt' => null,
            ];
        }

        $contact = &$organization['_contacts'][$contactKey];
        if (($contact['name'] ?? null) === null && $name !== null) {
            $contact['name'] = $name;
        }
        if (($contact['email'] ?? null) === null && $email !== null) {
            $contact['email'] = $email;
        }
        if (($contact['phone'] ?? null) === null && $phone !== null) {
            $contact['phone'] = $phone;
        }
        $contact['roles'][$role] = true;
        if ($message) {
            ++$contact['messageCount'];
        }

        $normalizedActivity = $this->normalizeDate($activityAt);
        if ($normalizedActivity !== null && (($contact['lastContactAt'] ?? null) === null || $normalizedActivity > $contact['lastContactAt'])) {
            $contact['lastContactAt'] = $normalizedActivity;
        }
        unset($contact);
    }

    /**
     * @param array<string, mixed> $organization
     *
     * @return array<string, mixed>
     */
    private function finalizeOrganization(array $organization): array
    {
        $roles = array_keys($organization['roles']);
        sort($roles);
        ksort($organization['applicationStatuses']);
        ksort($organization['positioningStatuses']);

        $offers = array_values($organization['_offers']);
        usort($offers, static fn (array $left, array $right): int => strcmp((string) ($right['activityAt'] ?? ''), (string) ($left['activityAt'] ?? '')));
        $offers = array_map(static function (array $offer): array {
            unset($offer['activityAt']);

            return $offer;
        }, array_slice($offers, 0, 5));

        $contacts = array_values($organization['_contacts']);
        $contacts = array_map(static function (array $contact): array {
            $contact['roles'] = array_keys($contact['roles']);
            sort($contact['roles']);

            return $contact;
        }, $contacts);
        usort($contacts, static function (array $left, array $right): int {
            $leftRecruiter = in_array('RECRUITER', $left['roles'], true) ? 1 : 0;
            $rightRecruiter = in_array('RECRUITER', $right['roles'], true) ? 1 : 0;
            if ($leftRecruiter !== $rightRecruiter) {
                return $rightRecruiter <=> $leftRecruiter;
            }

            return strcasecmp((string) ($left['name'] ?? $left['email'] ?? ''), (string) ($right['name'] ?? $right['email'] ?? ''));
        });

        return [
            'key' => $organization['key'],
            'name' => $organization['name'],
            'roles' => $roles,
            'offerCount' => count($organization['_offers']),
            'applicationCount' => count($organization['_applications']),
            'positioningCount' => count($organization['_positionings']),
            'messageCount' => count($organization['_messages']),
            'contactCount' => count($contacts),
            'applicationStatuses' => $organization['applicationStatuses'],
            'positioningStatuses' => $organization['positioningStatuses'],
            'lastActivityAt' => $organization['lastActivityAt'],
            'contacts' => $contacts,
            'latestOffers' => $offers,
        ];
    }

    /** @param array<string, mixed> $organization */
    private function rememberActivity(array &$organization, mixed $value): void
    {
        $date = $this->normalizeDate($value);
        if ($date !== null && (($organization['lastActivityAt'] ?? null) === null || $date > $organization['lastActivityAt'])) {
            $organization['lastActivityAt'] = $date;
        }
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    }

    private function normalizeEmail(mixed $email): ?string
    {
        if (!is_string($email)) {
            return null;
        }

        $email = mb_strtolower(trim($email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /** @return array{0: string|null, 1: string|null} */
    private function parseMailbox(string $mailbox): array
    {
        $mailbox = trim($mailbox);
        if ($mailbox === '') {
            return [null, null];
        }

        if (preg_match('/^\s*"?([^"<]*)"?\s*<\s*([^>]+)\s*>/u', $mailbox, $matches) === 1) {
            return [
                trim($matches[1], " \t\n\r\0\x0B\"") ?: null,
                $this->normalizeEmail($matches[2]),
            ];
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $mailbox, $matches) === 1) {
            return [null, $this->normalizeEmail($matches[0])];
        }

        return [null, null];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (\Exception) {
            return null;
        }
    }

    /** @param list<mixed> $fallbackParts */
    private function stableRecordKey(string $prefix, mixed $id, array $fallbackParts): string
    {
        if ((is_int($id) || is_string($id)) && (string) $id !== '') {
            return $prefix.':'.(string) $id;
        }

        return $prefix.':'.hash('sha256', json_encode($fallbackParts, JSON_THROW_ON_ERROR));
    }

    private function safeStatus(mixed $status): string
    {
        $status = is_string($status) ? trim($status) : '';

        return $status !== '' ? $status : 'UNKNOWN';
    }
}
