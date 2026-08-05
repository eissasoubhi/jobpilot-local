<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Crm\Application\OrganizationCrmDirectoryBuilder;
use App\Entity\Application;
use App\Entity\InboxMessage;
use App\Entity\JobOffer;
use App\Entity\Positioning;
use PHPUnit\Framework\TestCase;

final class OrganizationCrmDirectoryBuilderTest extends TestCase
{
    public function testBuildsOneOrganizationViewFromApplicationsPositioningsAndLinkedMessages(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Senior Symfony Developer',
            'company' => 'Acme Consulting',
            'clientName' => 'Final Client',
            'applicationEmail' => 'jobs@acme.test',
            'status' => 'PREPARED',
            'sourceUrl' => 'https://example.test/jobs/42',
        ]);
        $application = (new Application($job))->fill(['status' => 'INTERVIEW']);
        $positioning = (new Positioning())->fill([
            'agency' => 'ACME   Consulting',
            'finalClient' => 'Final Client',
            'recruiterName' => 'Jane Recruiter',
            'recruiterEmail' => 'jane@acme.test',
            'recruiterPhone' => '+33 6 00 00 00 00',
            'missionTitle' => 'Senior Symfony Developer',
            'status' => 'AGREEMENT_GIVEN',
        ]);
        $message = (new InboxMessage('gmail-42', 'thread-42'))->fill(
            'Jane Recruiter <jane@acme.test>',
            'Entretien technique',
            'Pouvez-vous confirmer votre disponibilité ?',
            new \DateTimeImmutable('2026-08-05T10:00:00+00:00'),
            'INTERVIEW_REQUEST',
            bodyText: 'Contenu privé non exposé dans le CRM.',
            actionRequired: true,
        );
        $message->associate($application, $job);

        $directory = (new OrganizationCrmDirectoryBuilder())->build(
            [$application],
            [$positioning],
            [$message],
        );

        self::assertSame(2, $directory['organizationCount']);
        self::assertSame(2, $directory['contactCount']);

        $acme = $this->organization($directory['organizations'], 'acme consulting');
        self::assertSame(['AGENCY', 'COMPANY'], $acme['roles']);
        self::assertSame(1, $acme['offerCount']);
        self::assertSame(1, $acme['applicationCount']);
        self::assertSame(1, $acme['positioningCount']);
        self::assertSame(1, $acme['messageCount']);
        self::assertSame(['INTERVIEW' => 1], $acme['applicationStatuses']);
        self::assertSame(['AGREEMENT_GIVEN' => 1], $acme['positioningStatuses']);
        self::assertCount(2, $acme['contacts']);

        $recruiter = $this->contact($acme['contacts'], 'jane@acme.test');
        self::assertSame('Jane Recruiter', $recruiter['name']);
        self::assertSame('+33 6 00 00 00 00', $recruiter['phone']);
        self::assertSame(['INBOX_CONTACT', 'RECRUITER'], $recruiter['roles']);
        self::assertSame(1, $recruiter['messageCount']);
        self::assertNotNull($recruiter['lastContactAt']);

        $applicationAddress = $this->contact($acme['contacts'], 'jobs@acme.test');
        self::assertSame(['APPLICATION_ADDRESS'], $applicationAddress['roles']);
        self::assertSame(0, $applicationAddress['messageCount']);

        $client = $this->organization($directory['organizations'], 'final client');
        self::assertSame(['CLIENT'], $client['roles']);
        self::assertSame(1, $client['applicationCount']);
        self::assertSame(1, $client['positioningCount']);
        self::assertSame(0, $client['contactCount']);

        self::assertStringNotContainsString('Contenu privé non exposé', json_encode($directory, JSON_THROW_ON_ERROR));
    }

    public function testDoesNotInventAContactFromAHeaderWithoutAnEmailAddress(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Backend Developer',
            'company' => 'Example Corp',
        ]);
        $application = new Application($job);
        $message = (new InboxMessage('gmail-43', 'thread-43'))->fill(
            'Recruitment notifications',
            'Application update',
            'Status changed',
            new \DateTimeImmutable('2026-08-05T11:00:00+00:00'),
            'APPLICATION_REPLY',
        );
        $message->associate($application, $job);

        $directory = (new OrganizationCrmDirectoryBuilder())->build([$application], [], [$message]);
        $organization = $this->organization($directory['organizations'], 'example corp');

        self::assertSame(0, $organization['contactCount']);
        self::assertSame(1, $organization['messageCount']);
    }

    /**
     * @param list<array<string, mixed>> $organizations
     *
     * @return array<string, mixed>
     */
    private function organization(array $organizations, string $key): array
    {
        foreach ($organizations as $organization) {
            if ($organization['key'] === $key) {
                return $organization;
            }
        }

        self::fail(sprintf('Organization "%s" was not found.', $key));
    }

    /**
     * @param list<array<string, mixed>> $contacts
     *
     * @return array<string, mixed>
     */
    private function contact(array $contacts, string $email): array
    {
        foreach ($contacts as $contact) {
            if ($contact['email'] === $email) {
                return $contact;
            }
        }

        self::fail(sprintf('Contact "%s" was not found.', $email));
    }
}
