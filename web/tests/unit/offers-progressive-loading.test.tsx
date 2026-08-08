import { act, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import JobsPage from '@/app/offres/page';
import type { Job } from '@/lib/types';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));
vi.mock('@/components/OfferApplicationSummary', () => ({
  OfferApplicationSummary: () => <div>Prepared application</div>,
}));

function cachedJob(): Job {
  return {
    id: 42,
    source: 'Local cache',
    sources: [],
    sourceCount: 1,
    title: 'Senior Symfony role already synchronized',
    company: 'Example',
    location: 'Paris',
    contractType: 'CDI',
    workMode: 'Hybride',
    language: 'fr',
    description: 'PHP Symfony',
    score: 88,
    scoreReasons: ['Strong match'],
    status: 'MATCHED',
  };
}

describe('Offers progressive loading', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('renders the local catalog before starting application loading and connector sync', async () => {
    let resolveJobs!: (jobs: Job[]) => void;
    const jobsPromise = new Promise<Job[]>((resolve) => {
      resolveJobs = resolve;
    });
    const applicationsPromise = new Promise<never>(() => undefined);
    const syncPromise = new Promise<never>(() => undefined);

    apiMock.mockImplementation((path: string) => {
      if (path === '/jobs') return jobsPromise;
      if (path === '/applications') return applicationsPromise;
      if (path === '/job-search/sync') return syncPromise;
      return Promise.reject(new Error(`Unexpected API call: ${path}`));
    });

    render(<JobsPage />);

    expect(apiMock).toHaveBeenCalledTimes(1);
    expect(apiMock).toHaveBeenCalledWith('/jobs');
    expect(screen.getByText('Chargement…')).toBeInTheDocument();

    await act(async () => {
      resolveJobs([cachedJob()]);
      await jobsPromise;
    });

    expect(await screen.findByText('Senior Symfony role already synchronized')).toBeInTheDocument();
    expect(screen.queryByText('Chargement…')).not.toBeInTheDocument();

    await waitFor(() => {
      expect(apiMock).toHaveBeenCalledWith('/applications');
      expect(apiMock).toHaveBeenCalledWith('/job-search/sync', { method: 'POST' });
    });

    expect(screen.getByText('Mise à jour en arrière-plan')).toBeInTheDocument();
    expect(screen.getByText(/Les offres déjà synchronisées restent visibles/)).toBeInTheDocument();
  });
});
