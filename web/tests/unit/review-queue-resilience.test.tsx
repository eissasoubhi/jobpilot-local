import { render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import ReviewQueuePage from '@/app/offres/review/page';
import type { Application } from '@/lib/types';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));
vi.mock('@/components/ApplicationGoalsPanel', () => ({ ApplicationGoalsPanel: () => null }));
vi.mock('@/components/ReviewQueueApplicationCard', () => ({
  ReviewQueueApplicationCard: ({ application }: { application: Application }) => (
    <h2>Carte complète {application.jobOffer.title}</h2>
  ),
}));

const readyApplication = {
  id: 1,
  channel: 'Préparation locale',
  status: 'READY_TO_SUBMIT',
  message: '',
  coverLetter: '',
  updatedAt: '2026-09-05T10:00:00+02:00',
  jobOffer: {
    id: 1,
    source: 'Test',
    title: 'Mission Symfony freelance',
    company: 'Example',
    location: 'Paris',
    contractType: 'Freelance',
    workMode: 'Hybride',
    language: 'fr',
    description: 'Description',
    score: 90,
    scoreReasons: [],
    status: 'PREPARED',
    sources: [],
    sourceCount: 1,
  },
} as Application;

describe('Review Queue resilience', () => {
  beforeEach(() => apiMock.mockReset());

  it('renders ready applications even if /jobs is unavailable', async () => {
    apiMock
      .mockResolvedValueOnce([readyApplication])
      .mockRejectedValueOnce(new Error('jobs unavailable'));

    render(<ReviewQueuePage />);

    await waitFor(() => expect(screen.getByText('Carte complète Mission Symfony freelance')).toBeInTheDocument());
    expect(screen.getByText('1 prête à envoyer')).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });
});
