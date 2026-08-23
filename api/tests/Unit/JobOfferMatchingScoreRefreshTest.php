<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\JobOffer;
use PHPUnit\Framework\TestCase;

final class JobOfferMatchingScoreRefreshTest extends TestCase
{
    public function testScoreOnlyRefreshPreservesPreparedWorkflowState(): void
    {
        $job = (new JobOffer())->fill([
            'title' => 'Senior Software Engineer',
            'description' => 'React PostgreSQL Docker.',
            'company' => 'Example',
            'location' => 'Remote',
            'contractType' => 'CDI',
            'workMode' => 'Remote',
            'publishedAt' => '2026-08-20T10:00:00+02:00',
        ]);
        $job->setEvaluation(
            'fr',
            63,
            ['Ancienne évaluation'],
            null,
            55000,
            'PREPARED',
            null,
        );

        $before = $job->toArray();

        $job->refreshMatchingScore(55, [
            'Compatibilité intitulé : 18/35',
            'Compétences : React, Docker, PostgreSQL',
        ]);

        $after = $job->toArray();

        self::assertSame(55, $after['score']);
        self::assertSame([
            'Compatibilité intitulé : 18/35',
            'Compétences : React, Docker, PostgreSQL',
        ], $after['scoreReasons']);
        self::assertSame('PREPARED', $after['status']);
        self::assertSame(55000, $after['proposedSalary']);
        self::assertSame($before['preparedAt'], $after['preparedAt']);
        self::assertSame($before['recommendedCv'], $after['recommendedCv']);
    }
}
