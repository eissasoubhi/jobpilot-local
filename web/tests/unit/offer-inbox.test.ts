import { describe, expect, it } from 'vitest';

import { matchesOfferInboxView } from '@/lib/offer-inbox';
import type { Application } from '@/lib/types';

function application(status: string): Application {
  return {
    id: 1,
    channel: 'Préparation locale',
    status,
    message: '',
    coverLetter: '',
    updatedAt: '2026-08-07T00:00:00+02:00',
    jobOffer: {
      id: 7,
      source: 'Test',
      title: 'Senior Symfony Developer',
      company: 'Example',
      sources: [],
      sourceCount: 1,
      location: 'Paris',
      contractType: 'CDI',
      workMode: 'Hybride',
      language: 'fr',
      description: 'Symfony',
      score: 80,
      scoreReasons: [],
      status: 'PREPARED',
    },
  };
}

describe('matchesOfferInboxView', () => {
  it('keeps submitted applications out of the actionable inbox', () => {
    expect(matchesOfferInboxView(application('SUBMITTED'), 'actionable')).toBe(false);
    expect(matchesOfferInboxView(application('READY_TO_SUBMIT'), 'actionable')).toBe(true);
    expect(matchesOfferInboxView(undefined, 'actionable')).toBe(true);
  });

  it('shows only submitted applications in the submitted view', () => {
    expect(matchesOfferInboxView(application('SUBMITTED'), 'submitted')).toBe(true);
    expect(matchesOfferInboxView(application('INTERVIEW'), 'submitted')).toBe(false);
    expect(matchesOfferInboxView(undefined, 'submitted')).toBe(false);
  });
});
