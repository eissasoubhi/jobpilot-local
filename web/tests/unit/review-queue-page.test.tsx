import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import ReviewQueuePage from '@/app/offres/review/page';
import type { Application } from '@/lib/types';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));
vi.mock('@/components/OfferApplicationSummary', () => ({
  OfferApplicationSummary: ({ application }: { application: Application }) => (
    <div>Résumé {application.jobOffer.title}</div>
  ),
}));

function application(id: number, title: string, status: string): Application {
  return {
    id,
    channel: 'Préparation locale',
    status,
    message: '',
    coverLetter: '',
    updatedAt: '2026-08-08T00:00:00+02:00',
    jobOffer: {
      id,
      source: 'Test',
      title,
      company: 'Example',
      location: 'Paris',
      contractType: 'CDI',
      workMode: 'Hybride',
      language: 'fr',
      description: 'Description',
      score: 80,
      scoreReasons: [],
      status: 'PREPARED',
      sources: [],
      sourceCount: 1,
    },
  } as Application;
}

describe('ReviewQueuePage', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('shows actionable applications one at a time with previous and next navigation', async () => {
    apiMock.mockResolvedValueOnce([
      application(1, 'First Symfony role', 'READY_TO_SUBMIT'),
      application(2, 'Already sent role', 'SUBMITTED'),
      application(3, 'Second Symfony role', 'READY_TO_SUBMIT'),
    ]);

    render(<ReviewQueuePage />);

    await waitFor(() => expect(screen.getByText('First Symfony role')).toBeInTheDocument());
    expect(screen.getByText('1 / 2')).toBeInTheDocument();
    expect(screen.queryByText('Already sent role')).not.toBeInTheDocument();
    expect(screen.queryByText('Second Symfony role')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Précédente' })).toBeDisabled();

    fireEvent.click(screen.getByRole('button', { name: 'Suivante' }));

    expect(screen.getByText('Second Symfony role')).toBeInTheDocument();
    expect(screen.getByText('2 / 2')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Suivante' })).toBeDisabled();
  });
});
