import { describe, expect, it } from 'vitest';

import { buildApplicationReporting } from '@/lib/application-reporting';
import type { Application } from '@/lib/types';

function application(id: number, status: string, source: string, submittedAt?: string): Application {
  return {
    id,
    status,
    channel: 'EMAIL',
    submittedAt,
    message: '',
    coverLetter: '',
    updatedAt: '2026-08-06T08:00:00+00:00',
    jobOffer: {
      id,
      source,
      sourceCode: source.toLowerCase(),
      title: `Role ${id}`,
      company: 'Example',
      sources: [],
      sourceCount: 1,
      location: 'Paris',
      contractType: 'CDI',
      workMode: 'Hybride',
      language: 'fr',
      description: '',
      score: 80,
      scoreReasons: [],
      status: 'NEW',
    },
  };
}

describe('buildApplicationReporting', () => {
  it('computes honest conversion totals and rates', () => {
    const result = buildApplicationReporting([
      application(1, 'SUBMITTED', 'France Travail', '2026-08-01T10:00:00+00:00'),
      application(2, 'INTERVIEW', 'France Travail', '2026-08-02T10:00:00+00:00'),
      application(3, 'REJECTED', 'Adzuna', '2026-08-03T10:00:00+00:00'),
      application(4, 'PREPARED', 'Adzuna'),
    ]);

    expect(result).toMatchObject({
      total: 4,
      submitted: 3,
      interviews: 1,
      rejected: 1,
      active: 3,
      submissionRate: 75,
      interviewRate: 33.3,
      rejectionRate: 33.3,
    });
    expect(result.bySource).toEqual([
      { source: 'Adzuna', total: 2, submitted: 1, interviews: 0, rejected: 1 },
      { source: 'France Travail', total: 2, submitted: 2, interviews: 1, rejected: 0 },
    ]);
  });

  it('returns zero rates for an empty data set', () => {
    expect(buildApplicationReporting([])).toMatchObject({
      total: 0,
      submitted: 0,
      submissionRate: 0,
      interviewRate: 0,
      rejectionRate: 0,
      bySource: [],
    });
  });
});
