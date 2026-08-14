import { describe, expect, it } from 'vitest';

import {
  applicationBadgeLabel,
  applicationStatusLabel,
  applicationStatusOptions,
  applicationStatusTone,
  filterApplications,
} from '@/lib/application-status';
import type { Application } from '@/lib/types';

function application(
  id: number,
  status: string,
  channel = 'Préparation locale',
): Application {
  return {
    id,
    channel,
    status,
    message: 'Message',
    coverLetter: '',
    updatedAt: '2026-08-06T12:00:00+02:00',
    jobOffer: {
      id,
      source: 'Test',
      title: `Poste ${id}`,
      company: `Entreprise ${id}`,
      sources: [],
      sourceCount: 1,
      location: 'Paris',
      contractType: 'CDI',
      workMode: 'Hybride',
      language: 'fr',
      description: 'Description',
      score: 80,
      scoreReasons: [],
      status: 'ELIGIBLE',
    },
  };
}

describe('application status helpers', () => {
  const applications = [
    application(1, 'READY_TO_SUBMIT'),
    application(2, 'READY_TO_SUBMIT'),
    application(3, 'SUBMITTED'),
    application(4, 'REJECTED'),
    application(5, 'CUSTOM_REVIEW'),
    application(6, 'OFFER_UNAVAILABLE'),
  ];

  it('filters prepared, submitted and unavailable applications independently', () => {
    expect(filterApplications(applications, 'READY_TO_SUBMIT').map(({ id }) => id)).toEqual([1, 2]);
    expect(filterApplications(applications, 'SUBMITTED').map(({ id }) => id)).toEqual([3]);
    expect(filterApplications(applications, 'OFFER_UNAVAILABLE').map(({ id }) => id)).toEqual([6]);
    expect(filterApplications(applications, 'ALL')).toHaveLength(6);
  });

  it('builds status options with counts and preserves unknown statuses', () => {
    const options = applicationStatusOptions(applications);

    expect(options[0]).toEqual({ value: 'ALL', label: 'Toutes les candidatures', count: 6 });
    expect(options).toContainEqual({ value: 'READY_TO_SUBMIT', label: 'Prêtes à envoyer', count: 2 });
    expect(options).toContainEqual({ value: 'SUBMITTED', label: 'Envoyées', count: 1 });
    expect(options).toContainEqual({ value: 'OFFER_UNAVAILABLE', label: 'Offres indisponibles', count: 1 });
    expect(options).toContainEqual({ value: 'INTERVIEW', label: 'Entretiens', count: 0 });
    expect(options).toContainEqual({ value: 'CUSTOM_REVIEW', label: 'Custom review', count: 1 });
  });

  it('uses clear labels and tones for tracking states', () => {
    expect(applicationStatusLabel('MISSING_CV')).toBe('CV manquant');
    expect(applicationStatusLabel('OFFER_UNAVAILABLE')).toBe('Offres indisponibles');
    expect(applicationStatusTone('SUBMITTED')).toBe('good');
    expect(applicationStatusTone('SUBMISSION_FAILED')).toBe('bad');
    expect(applicationStatusTone('SUBMISSION_PENDING')).toBe('warn');
    expect(applicationStatusTone('OFFER_UNAVAILABLE')).toBe('neutral');
    expect(applicationStatusTone('INTERVIEW')).toBe('blue');
  });

  it('distinguishes automatic Gmail submissions and unavailable offers in the badge', () => {
    expect(applicationBadgeLabel(application(1, 'SUBMITTED', 'Gmail automatique'))).toBe('ENVOYÉE AUTOMATIQUEMENT');
    expect(applicationBadgeLabel(application(2, 'SUBMITTED'))).toBe('ENVOYÉE');
    expect(applicationBadgeLabel(application(3, 'OFFER_UNAVAILABLE'))).toBe('OFFRE INDISPONIBLE');
  });
});
