<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\JobSourceOccurrence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SourceConversionReportApiTest extends WebTestCase
{
    public function testReportAttributesApplicationOutcomeAndCompensationToSourcesAndContractTypes(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $job = (new JobOffer())->fill([
            'source' => 'Report Test',
            'sourceCode' => 'report-primary',
            'externalId' => 'report-job-1',
            'title' => 'Senior Symfony Developer',
            'company' => 'Example',
            'contractType' => 'Freelance',
        ]);
        $job->setEvaluation('fr', 90, [], 500, 60000, 'PREPARED', null);
        $first = new JobSourceOccurrence($job, 'report-primary', 'Report Primary', 'report-job-1');
        $second = new JobSourceOccurrence($job, 'report-secondary', 'Report Secondary', 'report-job-2');
        $application = (new Application($job))->fill([
            'status' => 'INTERVIEW',
            'submittedAt' => '2026-08-06T09:00:00+00:00',
        ]);

        $em->persist($job);
        $em->persist($first);
        $em->persist($second);
        $em->persist($application);
        $em->flush();

        $client->request('GET', '/api/reporting/source-conversion');
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $rows = [];
        foreach ($payload['sources'] as $row) {
            $rows[$row['code']] = $row;
        }

        foreach (['report-primary', 'report-secondary'] as $code) {
            self::assertSame(1, $rows[$code]['offers']);
            self::assertSame(1, $rows[$code]['applications']);
            self::assertSame(1, $rows[$code]['submitted']);
            self::assertSame(1, $rows[$code]['responses']);
            self::assertSame(1, $rows[$code]['interviews']);
            self::assertEquals(100.0, $rows[$code]['interviewRate']);
            self::assertSame(1, $rows[$code]['tjmProposalCount']);
            self::assertSame(500, $rows[$code]['averageProposedTjm']);
            self::assertSame(1, $rows[$code]['salaryProposalCount']);
            self::assertSame(60000, $rows[$code]['averageProposedSalary']);
        }

        $contractTypes = [];
        foreach ($payload['contractTypes'] as $row) {
            $contractTypes[$row['code']] = $row;
        }

        self::assertArrayHasKey('freelance', $contractTypes);
        $contractType = $contractTypes['freelance'];
        self::assertSame('Freelance', $contractType['name']);
        self::assertGreaterThanOrEqual(1, $contractType['offers']);
        self::assertGreaterThanOrEqual(1, $contractType['applications']);
        self::assertGreaterThanOrEqual(1, $contractType['interviews']);
        self::assertGreaterThanOrEqual(1, $contractType['tjmProposalCount']);
        self::assertGreaterThanOrEqual(1, $contractType['salaryProposalCount']);
    }
}
