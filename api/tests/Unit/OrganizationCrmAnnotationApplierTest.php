<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Crm\Application\OrganizationCrmAnnotationApplier;
use App\Entity\CrmOrganizationAnnotation;
use PHPUnit\Framework\TestCase;

final class OrganizationCrmAnnotationApplierTest extends TestCase
{
    public function testAppliesDisplayNameAndNoteWithoutChangingTheStableKeyOrSourceName(): void
    {
        $directory = $this->directory();
        $annotation = (new CrmOrganizationAnnotation('acme consulting'))->update(
            'ACME Consulting France',
            'Interlocuteur fiable pour les missions Symfony.',
        );

        $result = (new OrganizationCrmAnnotationApplier())->apply($directory, [$annotation]);
        $organization = $result['organizations'][0];

        self::assertSame(1, $result['annotationCount']);
        self::assertSame('acme consulting', $organization['key']);
        self::assertSame('Acme Consulting', $organization['sourceName']);
        self::assertSame('ACME Consulting France', $organization['name']);
        self::assertSame('Interlocuteur fiable pour les missions Symfony.', $organization['annotation']['note']);
        self::assertArrayNotHasKey('note', $organization['contacts'][0]);
    }

    public function testIgnoresAStaleAnnotationForAnOrganizationThatNoLongerExists(): void
    {
        $annotation = (new CrmOrganizationAnnotation('deleted organization'))->update('Deleted', 'Stale note');

        $result = (new OrganizationCrmAnnotationApplier())->apply($this->directory(), [$annotation]);
        $organization = $result['organizations'][0];

        self::assertSame(0, $result['annotationCount']);
        self::assertSame('Acme Consulting', $organization['name']);
        self::assertSame('Acme Consulting', $organization['sourceName']);
        self::assertNull($organization['annotation']);
    }

    /** @return array<string, mixed> */
    private function directory(): array
    {
        return [
            'generatedAt' => '2026-08-05T21:00:00+00:00',
            'organizationCount' => 1,
            'contactCount' => 1,
            'organizations' => [[
                'key' => 'acme consulting',
                'name' => 'Acme Consulting',
                'roles' => ['COMPANY'],
                'offerCount' => 1,
                'applicationCount' => 1,
                'positioningCount' => 0,
                'messageCount' => 1,
                'contactCount' => 1,
                'applicationStatuses' => ['INTERVIEW' => 1],
                'positioningStatuses' => [],
                'lastActivityAt' => '2026-08-05T10:00:00+00:00',
                'contacts' => [[
                    'key' => 'jane@acme.test',
                    'name' => 'Jane Recruiter',
                    'email' => 'jane@acme.test',
                    'phone' => null,
                    'roles' => ['RECRUITER'],
                    'messageCount' => 1,
                    'lastContactAt' => '2026-08-05T10:00:00+00:00',
                ]],
                'latestOffers' => [],
            ]],
        ];
    }
}
