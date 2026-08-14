import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { ReviewQueueApplicationCard } from '@/components/ReviewQueueApplicationCard';
import type { Application } from '@/lib/types';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({
  API_URL: '/api',
  api: apiMock,
}));
vi.mock('@/components/CoverLetterDrawer', () => ({ CoverLetterDrawer: () => null }));
vi.mock('@/components/ReviewQueueTechnologyComparison', () => ({ ReviewQueueTechnologyComparison: () => null }));
vi.mock('@/lib/job-publication', () => ({
  offerPublicationTiming: () => ({
    label: 'Publiée il y a 10 jours',
    exactLabel: 'Publiée le 4 août 2026 à 18:00',
    stale: true,
  }),
}));

function readyApplication(): Application {
  return {
    id: 41,
    channel: 'Préparation locale',
    status: 'READY_TO_SUBMIT',
    message: 'Bonjour',
    coverLetter: 'Lettre de motivation',
    updatedAt: '2026-08-14T18:00:00+02:00',
    jobOffer: {
      id: 7,
      source: 'France Travail',
      sourceUrl: 'https://example.test/jobs/7',
      title: 'Senior Symfony Developer',
      company: 'Example',
      location: 'Paris',
      contractType: 'CDI',
      workMode: 'Hybride',
      language: 'fr',
      description: 'Mission Symfony et React.',
      publishedAt: '2026-08-04T18:00:00+02:00',
      discoveredAt: '2026-08-05T09:00:00+02:00',
      score: 91,
      scoreReasons: ['Symfony correspond au profil.'],
      status: 'PREPARED',
      sources: [],
      sourceCount: 1,
    },
  } as Application;
}

afterEach(() => {
  vi.restoreAllMocks();
  apiMock.mockReset();
});

describe('ReviewQueueApplicationCard offer availability', () => {
  it('shows publication age and warns when the offer is old', () => {
    render(<ReviewQueueApplicationCard application={readyApplication()} />);

    const timing = screen.getByText('Publiée il y a 10 jours');
    expect(timing).toBeInTheDocument();
    expect(timing).toHaveAttribute('title', 'Publiée le 4 août 2026 à 18:00');
    expect(screen.getByText('Offre ancienne')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Offre indisponible' })).toBeInTheDocument();
  });

  it('marks an unavailable offer through the dedicated endpoint after confirmation', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const onApplicationUpdated = vi.fn();
    const original = readyApplication();
    const updated: Application = {
      ...original,
      status: 'OFFER_UNAVAILABLE',
      jobOffer: { ...original.jobOffer, status: 'UNAVAILABLE' },
    };
    apiMock.mockResolvedValue(updated);

    render(
      <ReviewQueueApplicationCard
        application={original}
        onApplicationUpdated={onApplicationUpdated}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Offre indisponible' }));

    await waitFor(() => {
      expect(apiMock).toHaveBeenCalledWith('/applications/41/offer-unavailable', { method: 'POST' });
    });
    expect(window.confirm).toHaveBeenCalledWith(expect.stringContaining('retirée de la Review Queue'));
    expect(onApplicationUpdated).toHaveBeenCalledWith(updated);
  });

  it('does nothing when the user cancels the action', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    render(<ReviewQueueApplicationCard application={readyApplication()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Offre indisponible' }));

    expect(apiMock).not.toHaveBeenCalled();
  });
});
