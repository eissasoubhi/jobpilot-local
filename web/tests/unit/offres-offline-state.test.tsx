import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));

import JobsPage from '@/app/offres/page';

function successfulSync() {
  return {
    job: {
      id: 'sync-1',
      status: 'success',
      queuedAt: '2026-08-25T10:00:00Z',
      startedAt: '2026-08-25T10:00:01Z',
      finishedAt: '2026-08-25T10:00:02Z',
      updatedAt: '2026-08-25T10:00:02Z',
      result: {
        providers: [],
        lastSyncedAt: '2026-08-25T10:00:02Z',
        nextSyncAt: null,
      },
    },
  };
}

describe('Offers offline state', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('shows one retryable offline state instead of healthy worker and empty catalog signals', async () => {
    apiMock.mockRejectedValueOnce(new Error('API locale indisponible'));

    render(<JobsPage />);

    expect(await screen.findByText('JobPilot ne peut pas charger les offres')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Réessayer' })).toBeInTheDocument();
    expect(screen.getByText(/API locale indisponible/)).toBeInTheDocument();
    expect(screen.queryByText('Worker actif')).not.toBeInTheDocument();
    expect(screen.queryByText('Données locales affichées')).not.toBeInTheDocument();
    expect(screen.queryByText('Aucune offre ne correspond aux filtres sélectionnés.')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Rechercher maintenant' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Ajouter une offre' })).toBeDisabled();
    expect(apiMock).toHaveBeenCalledTimes(1);
  });

  it('retries the workspace without reloading the browser and resumes dependent calls after recovery', async () => {
    let jobAttempts = 0;
    apiMock.mockImplementation((path: string) => {
      if (path === '/jobs') {
        jobAttempts += 1;
        if (jobAttempts === 1) return Promise.reject(new Error('Connexion refusée'));
        return Promise.resolve([]);
      }
      if (path === '/applications') return Promise.resolve([]);
      if (path === '/job-search/sync') return Promise.resolve(successfulSync());

      return Promise.reject(new Error(`Unexpected API call: ${path}`));
    });

    render(<JobsPage />);

    const retry = await screen.findByRole('button', { name: 'Réessayer' });
    fireEvent.click(retry);

    await waitFor(() => {
      expect(screen.queryByText('JobPilot ne peut pas charger les offres')).not.toBeInTheDocument();
    });

    expect(await screen.findByText('Aucune offre ne correspond aux filtres sélectionnés.')).toBeInTheDocument();
    expect(screen.getByText('Données locales affichées')).toBeInTheDocument();
    expect(apiMock).toHaveBeenCalledWith('/applications');
    expect(apiMock).toHaveBeenCalledWith('/job-search/sync', { method: 'POST' });
    expect(jobAttempts).toBeGreaterThanOrEqual(2);
  });
});
