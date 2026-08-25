import { describe, expect, it } from 'vitest';

import { presentJobTimeline } from '@/lib/application-timeline';

describe('presentJobTimeline', () => {
  it('presents persisted business events in reverse chronological order', () => {
    const events = presentJobTimeline([
      {
        id: 14,
        jobOfferId: 30,
        applicationId: 12,
        type: 'APPLICATION_SUBMITTED',
        source: 'manual-status',
        payload: { previousStatus: 'DRAFT' },
        occurredAt: '2026-08-02T09:01:00+00:00',
        recordedAt: '2026-08-02T09:01:01+00:00',
      },
      {
        id: 15,
        jobOfferId: 30,
        applicationId: 12,
        type: 'INTERVIEW',
        source: 'gmail-inbox',
        payload: { category: 'INTERVIEW_REQUEST' },
        occurredAt: '2026-08-04T11:00:00+00:00',
        recordedAt: '2026-08-04T11:00:01+00:00',
      },
    ]);

    expect(events.map((event) => event.title)).toEqual([
      'Entretien proposé',
      'Candidature envoyée',
    ]);
    expect(events[0]).toMatchObject({
      key: 'timeline-15',
      tone: 'good',
      description: 'Une invitation à un entretien a été reçue.',
    });
  });

  it('does not invent events when the persistent timeline is empty', () => {
    expect(presentJobTimeline([])).toEqual([]);
  });
});
