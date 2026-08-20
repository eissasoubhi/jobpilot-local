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

  it('removes an unavailable offer from the queue without submitting an application', async () => {
    const first = application(1, 'First role');
    const second = application(2, 'Second role');
    mockInitialLoad([first, second]);
    apiMock.mockResolvedValueOnce({
      ...first,
      status: 'OFFER_UNAVAILABLE',
      jobOffer: { ...first.jobOffer, status: 'UNAVAILABLE' },
    });
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    render(<ReviewQueuePage />);
    await waitFor(() => expect(screen.getByRole('heading', { name: 'First role' })).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'N’est plus disponible' }));

    await waitFor(() => expect(apiMock).toHaveBeenLastCalledWith('/applications/1/offer-unavailable', {
      method: 'POST',
    }));
    expect(window.confirm).toHaveBeenCalledWith(expect.stringContaining('aucune candidature ne sera envoyée'));
    expect(apiMock).not.toHaveBeenCalledWith('/applications/1', expect.objectContaining({ method: 'PATCH' }));
    expect(screen.queryByRole('heading', { name: 'First role' })).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Second role' })).toBeInTheDocument();
    expect(screen.getByText('1 / 1')).toBeInTheDocument();
  });

  it('keeps the offer in the queue when the unavailable decision is cancelled', async () => {
    const first = application(1, 'First role');
    mockInitialLoad([first]);
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    render(<ReviewQueuePage />);
    await waitFor(() => expect(screen.getByRole('heading', { name: 'First role' })).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'N’est plus disponible' }));

    expect(apiMock).toHaveBeenCalledTimes(2);
    expect(screen.getByRole('heading', { name: 'First role' })).toBeInTheDocument();
  });
});
