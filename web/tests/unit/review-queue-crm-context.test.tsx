import { render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import ReviewQueuePage from '@/app/offres/review/page';
import type { Application, Job } from '@/lib/types';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));
vi.mock('@/components/ReviewQueueApplicationCard', () => ({
  ReviewQueueApplicationCard: ({ application }: { application: Application }) => (
    <div>{application.jobOffer.title}</div>
  ),
}));

function readyApplication(clientName: string | null): Application {
  return {
    id: 1,
    channel: 'Préparation locale',
    status: 'READY_TO_SUBMIT',
    message: '',
    coverLetter: '',
    updatedAt: '2026-08-12T12:00:00+02:00',
    jobOffer: {
      id: 1,
      source: 'Test',
      title: 'Senior Symfony Developer',
      company: 'Agence Example',
      clientName,
      location: 'Paris',
      contractType: 'Freelance',
      workMode: 'Hybride',
      language: 'fr',
      description: 'Mission Symfony',
      score: 92,
      scoreReasons: [],
      status: 'PREPARED',
      sources: [],
      sourceCount: 1,
    },
  } as Application;
}

function mockLoad(application: Application): void {
  apiMock
    .mockResolvedValueOnce([application])
    .mockResolvedValueOnce([application.jobOffer] as Job[]);
}

describe('ReviewQueuePage CRM context shortcut', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('opens the final client context when the offer exposes one', async () => {
    mockLoad(readyApplication('Client Final & Co'));

    render(<ReviewQueuePage />);

    const link = await screen.findByRole('link', { name: 'Ouvrir le contexte CRM de Client Final & Co' });
    expect(link).toHaveAttribute('href', '/crm?q=Client%20Final%20%26%20Co');
  });

  it('falls back to the offer company when no final client is known', async () => {
    mockLoad(readyApplication(null));

    render(<ReviewQueuePage />);

    await waitFor(() => {
      expect(screen.getByRole('link', { name: 'Ouvrir le contexte CRM de Agence Example' }))
        .toHaveAttribute('href', '/crm?q=Agence%20Example');
    });
  });
});
