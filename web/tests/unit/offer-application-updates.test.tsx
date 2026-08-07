import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { OfferApplicationSummary } from '@/components/OfferApplicationSummary';
import type { Application } from '@/lib/types';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));

function application(status = 'READY_TO_SUBMIT'): Application {
  return {
    id: 42,
    channel: 'Préparation locale',
    status,
    message: 'Bonjour',
    coverLetter: '',
    compensationAnswer: null,
    updatedAt: '2026-08-07T00:00:00+02:00',
    cvDocument: null,
    jobOffer: {
      id: 7,
      source: 'Test',
      sourceUrl: 'https://example.test/jobs/7',
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

describe('OfferApplicationSummary updates', () => {
  it('notifies the Offers workspace immediately after marking an application submitted', async () => {
    const submitted = application('SUBMITTED');
    const onApplicationUpdated = vi.fn();
    apiMock.mockResolvedValueOnce(submitted);

    render(
      <OfferApplicationSummary
        application={application()}
        onApplicationUpdated={onApplicationUpdated}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Examiner' }));
    fireEvent.click(screen.getByRole('button', { name: 'J’ai envoyé la candidature' }));

    await waitFor(() => expect(onApplicationUpdated).toHaveBeenCalledWith(submitted));
  });
});
