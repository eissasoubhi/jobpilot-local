import { describe, expect, it } from 'vitest';

import { offerPublicationTiming } from '@/lib/job-publication';

describe('offerPublicationTiming', () => {
  const now = new Date('2026-08-14T18:00:00+02:00');

  it('uses the publication timestamp when available', () => {
    const timing = offerPublicationTiming('2026-08-14T13:00:00+02:00', null, now);

    expect(timing.label).toBe('Publiée il y a 5 h');
    expect(timing.exactLabel).toContain('Publiée le');
    expect(timing.stale).toBe(false);
  });

  it('flags offers older than seven days as stale', () => {
    const timing = offerPublicationTiming('2026-08-04T18:00:00+02:00', null, now);

    expect(timing.label).toBe('Publiée il y a 10 jours');
    expect(timing.stale).toBe(true);
  });

  it('falls back to discovery time without pretending it is the publication date', () => {
    const timing = offerPublicationTiming(null, '2026-08-13T18:00:00+02:00', now);

    expect(timing.label).toBe('Détectée il y a 1 jour');
    expect(timing.stale).toBe(false);
  });

  it('is explicit when no reliable timestamp exists', () => {
    expect(offerPublicationTiming(null, null, now)).toEqual({
      label: 'Publication inconnue',
      exactLabel: null,
      stale: false,
    });
  });
});
