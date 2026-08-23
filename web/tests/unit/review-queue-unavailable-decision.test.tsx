import type { Ref } from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import ReviewQueuePage from '@/app/offres/review/page';
import type { Application, Job } from '@/lib/types';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));
vi.mock('@/components/ApplicationGoalsPanel', () => ({
  ApplicationGoalsPanel: () => null,
}));
vi.mock('@/components/ReviewQueueApplicationCard', () => ({
  ReviewQueueApplicationCard: ({
    application,
    headingRef,
  }: {
    application: Application;
    headingRef?: Ref<HTMLHeadingElement>;
  }) => <h2 ref={headingRef} tabIndex={-1}>{application.jobOffer.title}</h2>,
}));

function application(id: number, title: string, status = 'READY_TO_SUBMIT'): Application {
  return {
    id,
    channel: 'Préparation locale',
    status,
    message: '',
    coverLetter: '',
    updatedAt: '2026-08-20T16:00:00+02:00',
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

function mockInitialLoad(applications: Application[]): void {
  const jobs = applications.map((item) => item.jobOffer) as Job[];
  apiMock
    .mockResolvedValueOnce(applications)
    .mockResolvedValueOnce(jobs);
}

describe('ReviewQueue unavailable decision', () => {
  beforeEach(() => {
    apiMock.mockReset();
    vi.restoreAllMocks();
  });

  it('marks an unavailable offer immediately without a confirmation or submission', async () => {
    const first = application(1, 'First role');
    const second = application(2, 'Second role');
    const confirmSpy = vi.spyOn(window, 'confirm');
    mockInitialLoad([first, second]);
    apiMock.mockResolvedValueOnce({
      ...first,
      status: 'OFFER_UNAVAILABLE',
      jobOffer: { ...first.jobOffer, status: 'UNAVAILABLE' },
    });

    render(<ReviewQueuePage />);
    await waitFor(() => expect(screen.getByRole('heading', { name: 'First role' })).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'N’est plus disponible' }));

    await waitFor(() => expect(apiMock).toHaveBeenLastCalledWith('/applications/1/offer-unavailable', {
      method: 'POST',
    }));
    expect(confirmSpy).not.toHaveBeenCalled();
    expect(apiMock).not.toHaveBeenCalledWith('/applications/1', expect.objectContaining({ method: 'PATCH' }));
    expect(screen.queryByRole('heading', { name: 'First role' })).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Second role' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Annuler la dernière action sur First role' })).toBeInTheDocument();
  });

  it('undoes the unavailable decision and restores the offer at its previous position', async () => {
    const first = application(1, 'First role');
    const second = application(2, 'Second role');
    const unavailable: Application = {
      ...first,
      status: 'OFFER_UNAVAILABLE',
      jobOffer: { ...first.jobOffer, status: 'UNAVAILABLE' },
    } as Application;
    mockInitialLoad([first, second]);
    apiMock
      .mockResolvedValueOnce(unavailable)
      .mockResolvedValueOnce(first);

    render(<ReviewQueuePage />);
    await waitFor(() => expect(screen.getByRole('heading', { name: 'First role' })).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'N’est plus disponible' }));
    await waitFor(() => expect(screen.getByRole('heading', { name: 'Second role' })).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'Annuler la dernière action sur First role' }));

    await waitFor(() => expect(apiMock).toHaveBeenLastCalledWith('/applications/1/review-decision/undo', {
      method: 'POST',
    }));
    expect(await screen.findByRole('heading', { name: 'First role' })).toBeInTheDocument();
    expect(screen.getByText('1 / 2')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Annuler la dernière action sur First role' })).not.toBeInTheDocument();
  });

  it('keeps undo available when the decision empties the queue', async () => {
    const only = application(1, 'Only role');
    mockInitialLoad([only]);
    apiMock
      .mockResolvedValueOnce({
        ...only,
        status: 'OFFER_UNAVAILABLE',
        jobOffer: { ...only.jobOffer, status: 'UNAVAILABLE' },
      })
      .mockResolvedValueOnce(only);

    render(<ReviewQueuePage />);
    await waitFor(() => expect(screen.getByRole('heading', { name: 'Only role' })).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'N’est plus disponible' }));

    expect(await screen.findByText('Aucune candidature prête à envoyer dans la Review Queue.')).toBeInTheDocument();
    const undo = screen.getByRole('button', { name: 'Annuler la dernière action sur Only role' });
    expect(undo).toBeInTheDocument();

    fireEvent.click(undo);
    expect(await screen.findByRole('heading', { name: 'Only role' })).toBeInTheDocument();
  });
});
