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
    public function testReportAttributesApplicationOutcomeCompensationAndMatchingQuality(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $job = (new JobOffer())->fill([
            'source' => 'Report Test',
            'sourceCode' => 'report-primary',
            'externalId' => 'report-job-1',
            'title' => 'Senior Symfony Developer',
            'company' => 'Example',
            'contractType' => 'Reporting Freelance',
            'workMode' => 'Reporting Hybrid',
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
            self::assertEquals(90.0, $rows[$code]['averageMatchingScore']);
            self::assertSame(1, $rows[$code]['strongMatches']);
            self::assertEquals(100.0, $rows[$code]['strongMatchRate']);
            self::assertSame(1, $rows[$code]['tjmProposalCount']);
            self::assertSame(500, $rows[$code]['averageProposedTjm']);
            self::assertSame(1, $rows[$code]['salaryProposalCount']);
            self::assertSame(60000, $rows[$code]['averageProposedSalary']);
        }

        $contractTypes = [];
        foreach ($payload['contractTypes'] as $row) {
            $contractTypes[$row['code']] = $row;
        }

        self::assertArrayHasKey('reporting freelance', $contractTypes);
        $contractType = $contractTypes['reporting freelance'];
        self::assertSame('Reporting Freelance', $contractType['name']);
        self::assertSame(1, $contractType['offers']);
        self::assertSame(1, $contractType['applications']);
        self::assertSame(1, $contractType['interviews']);
        self::assertEquals(90.0, $contractType['averageMatchingScore']);
        self::assertSame(1, $contractType['strongMatches']);
        self::assertEquals(100.0, $contractType['strongMatchRate']);
        self::assertSame(1, $contractType['tjmProposalCount']);
        self::assertSame(1, $contractType['salaryProposalCount']);

        $workModes = [];
        foreach ($payload['workModes'] as $row) {
            $workModes[$row['code']] = $row;
        }

        self::assertArrayHasKey('reporting hybrid', $workModes);
        $workMode = $workModes['reporting hybrid'];
        self::assertSame('Reporting Hybrid', $workMode['name']);
        self::assertSame(1, $workMode['offers']);
        self::assertSame(1, $workMode['applications']);
        self::assertSame(1, $workMode['interviews']);
        self::assertEquals(90.0, $workMode['averageMatchingScore']);
        self::assertSame(1, $workMode['strongMatches']);
        self::assertEquals(100.0, $workMode['strongMatchRate']);
        self::assertSame(1, $workMode['tjmProposalCount']);
        self::assertSame(1, $workMode['salaryProposalCount']);
    }
}
