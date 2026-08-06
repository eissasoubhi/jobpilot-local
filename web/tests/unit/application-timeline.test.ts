import { describe, expect, it } from 'vitest';

import { buildApplicationTimeline } from '@/lib/application-timeline';

describe('buildApplicationTimeline', () => {
  it('combines stored application dates and associated Gmail messages in reverse chronological order', () => {
    const events = buildApplicationTimeline(
      {
        id: 12,
        status: 'INTERVIEW',
        channel: 'Gmail automatique',
        createdAt: '2026-08-01T08:00:00+00:00',
        updatedAt: '2026-08-04T12:00:00+00:00',
        submissionAttemptedAt: '2026-08-02T09:00:00+00:00',
        submittedAt: '2026-08-02T09:01:00+00:00',
      },
      [
        {
          id: 90,
          application: { id: 12, status: 'INTERVIEW' },
          category: 'INTERVIEW_REQUEST',
          subject: 'Entretien jeudi',
          sender: 'recruteur@example.com',
          receivedAt: '2026-08-04T11:00:00+00:00',
          gmailUrl: 'https://mail.google.com/mail/u/0/#inbox/90',
          actionRequired: true,
          processed: false,
        },
        {
          id: 91,
          application: { id: 99, status: 'REJECTED' },
          category: 'REJECTION',
          subject: 'Autre candidature',
          sender: 'other@example.com',
          receivedAt: '2026-08-05T11:00:00+00:00',
          gmailUrl: null,
          actionRequired: false,
          processed: false,
        },
      ],
    );

    expect(events.map((event) => event.kind)).toEqual([
      'CURRENT_STATUS',
      'MESSAGE',
      'SUBMITTED',
      'SUBMISSION_ATTEMPT',
      'CREATED',
    ]);
    expect(events[1]).toMatchObject({
      title: 'Invitation à un entretien reçue',
      tone: 'good',
      href: 'https://mail.google.com/mail/u/0/#inbox/90',
    });
    expect(events).toHaveLength(5);
  });

  it('exposes a failed submission attempt without inventing a submitted event', () => {
    const events = buildApplicationTimeline(
      {
        id: 7,
        status: 'SUBMISSION_FAILED',
        channel: 'Préparation locale',
        createdAt: '2026-08-01T08:00:00+00:00',
        updatedAt: '2026-08-01T08:05:00+00:00',
        submissionAttemptedAt: '2026-08-01T08:04:00+00:00',
        submissionError: 'Gmail indisponible',
      },
      [],
    );

    expect(events.find((event) => event.kind === 'SUBMISSION_ATTEMPT')).toMatchObject({
      title: 'Tentative d’envoi échouée',
      description: 'Gmail indisponible',
      tone: 'bad',
    });
    expect(events.some((event) => event.kind === 'SUBMITTED')).toBe(false);
  });
});
