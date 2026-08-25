import { describe, expect, it } from 'vitest';

import { jobTargetCompany } from '@/lib/job-target-company';
import type { Job } from '@/lib/types';

function job(overrides: Partial<Job> = {}): Job {
  return {
    id: 1,
    source: 'France Travail',
    sourceCode: 'france-travail',
    sourceUrl: 'https://candidat.francetravail.fr/offres/recherche/detail/1',
    title: 'Développeur Symfony',
    company: 'Acme',
    location: 'Paris',
    contractType: 'CDI',
    workMode: 'Hybride',
    language: 'fr',
    description: 'Symfony PHP',
    score: 90,
    scoreReasons: [],
    status: 'PREPARED',
    sources: [],
    sourceCount: 1,
    ...overrides,
  };
}

describe('jobTargetCompany', () => {
  it('keeps a genuine employer name', () => {
    expect(jobTargetCompany(job())).toBe('Acme');
  });

  it('drops a company that is actually the source platform', () => {
    expect(jobTargetCompany(job({
      source: 'Indeed',
      sourceCode: 'indeed-assisted',
      sourceUrl: 'https://fr.indeed.com/viewjob?jk=1',
      company: 'Indeed',
    }))).toBe('');
  });

  it('prefers a known client over the platform company', () => {
    expect(jobTargetCompany(job({
      source: 'Indeed',
      sourceCode: 'indeed-assisted',
      sourceUrl: 'https://fr.indeed.com/viewjob?jk=1',
      company: 'Indeed',
      clientName: 'Proton',
    }))).toBe('Proton');
  });
});
