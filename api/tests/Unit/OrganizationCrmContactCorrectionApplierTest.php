<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Crm\Application\OrganizationCrmContactCorrectionApplier;
use App\Entity\CrmContactCorrection;
use PHPUnit\Framework\TestCase;

final class OrganizationCrmContactCorrectionApplierTest extends TestCase
{
    public function testAppliesCorrectionsWhilePreservingEverySourceValueAndStableKey(): void
    {
        $correction = (new CrmContactCorrection('acme consulting', 'jane@acme.test'))->update(
            'Jane Recruiter France',
            'jane.france@acme.test',
            '+33 6 10 20 30 40',
        );

        $result = (new OrganizationCrmContactCorrectionApplier())->apply(
            $this->directory(),
            [$correction],
        );
        $contact = $result['organizations'][0]['contacts'][0];

        self::assertSame(1, $result['contactCorrectionCount']);
        self::assertSame('jane@acme.test', $contact['key']);
        self::assertSame('Jane Recruiter', $contact['sourceName']);
        self::assertSame('jane@acme.test', $contact['sourceEmail']);
        self::assertSame('+33 6 00 00 00 00', $contact['sourcePhone']);
        self::assertSame('Jane Recruiter France', $contact['name']);
        self::assertSame('jane.france@acme.test', $contact['email']);
        self::assertSame('+33 6 10 20 30 40', $contact['phone']);
        self::assertSame('Jane Recruiter France', $contact['correction']['correctedName']);
        self::assertNotNull($contact['correction']['updatedAt']);
    }

    public function testKeepsSourceValuesWhenOnlyOneFieldIsCorrected(): void
    {
        $correction = (new CrmContactCorrection('acme consulting', 'jane@acme.test'))->update(
            'Jane R.',
            null,
            null,
        );

        $result = (new OrganizationCrmContactCorrectionApplier())->apply(
            $this->directory(),
            [$correction],
        );
        $contact = $result['organizations'][0]['contacts'][0];

        self::assertSame('Jane R.', $contact['name']);
        self::assertSame('jane@acme.test', $contact['email']);
        self::assertSame('+33 6 00 00 00 00', $contact['phone']);
    }

    public function testIgnoresAStaleCorrectionForAMissingContact(): void
    {
        $correction = (new CrmContactCorrection('acme consulting', 'deleted@acme.test'))->update(
            'Deleted Contact',
            null,
            null,
        );

        $result = (new OrganizationCrmContactCorrectionApplier())->apply(
            $this->directory(),
            [$correction],
        );
        $contact = $result['organizations'][0]['contacts'][0];

        self::assertSame(0, $result['contactCorrectionCount']);
        self::assertSame('Jane Recruiter', $contact['name']);
        self::assertSame('Jane Recruiter', $contact['sourceName']);
        self::assertNull($contact['correction']);
    }

    /** @return array<string, mixed> */
    private function directory(): array
    {
        return [
            'generatedAt' => '2026-08-05T22:30:00+00:00',
            'organizationCount' => 1,
            'contactCount' => 1,
            'annotationCount' => 0,
            'organizations' => [[
                'key' => 'acme consulting',
                'name' => 'Acme Consulting',
                'sourceName' => 'Acme Consulting',
                'annotation' => null,
                'roles' => ['COMPANY'],
                'offerCount' => 1,
                'applicationCount' => 1,
                'positioningCount' => 1,
                'messageCount' => 1,
                'contactCount' => 1,
                'applicationStatuses' => ['INTERVIEW' => 1],
                'positioningStatuses' => ['AGREEMENT_GIVEN' => 1],
                'lastActivityAt' => '2026-08-05T10:00:00+00:00',
                'contacts' => [[
                    'key' => 'jane@acme.test',
                    'name' => 'Jane Recruiter',
                    'email' => 'jane@acme.test',
                    'phone' => '+33 6 00 00 00 00',
                    'roles' => ['INBOX_CONTACT', 'RECRUITER'],
                    'messageCount' => 1,
                    'lastContactAt' => '2026-08-05T10:00:00+00:00',
                ]],
                'latestOffers' => [],
            ]],
        ];
    }
}
