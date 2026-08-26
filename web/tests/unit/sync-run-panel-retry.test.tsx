import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { SyncRunPanel } from '@/components/SyncRunPanel';

const apiMock = vi.fn();

vi.mock('next/navigation', () => ({
  usePathname: () => '/offres',
}));

vi.mock('@/lib/api', () => ({
  api: (...args: unknown[]) => apiMock(...args),
}));

const snapshot = {
  job: {
    id: 'run-1',
    status: 'partial',
    queuedAt: '2026-08-25T12:00:00Z',
    startedAt: '2026-08-25T12:00:01Z',
    finishedAt: '2026-08-25T12:01:00Z',
    updatedAt: '2026-08-25T12:01:00Z',
    result: { received: 10, imported: 2, failed: 1 },
    error: null,
  },
  worker: { status: 'active', updatedAt: '2026-08-25T12:01:00Z' },
  connectors: [
    {
      code: 'apec',
      name: 'Apec',
      enabled: true,
      configured: true,
      collectionAllowed: true,
      status: 'ERROR',
      lastSyncedAt: '2026-08-25T12:00:30Z',
      lastError: 'Erreur temporaire Apec',
      lastResult: { received: 0, imported: 0, merged: 0, duplicates: 0, failed: 1 },
    },
    {
      code: 'adzuna',
      name: 'Adzuna',
      enabled: true,
      configured: true,
      collectionAllowed: true,
      status: 'READY',
      lastSyncedAt: '2026-08-25T12:00:40Z',
      lastError: null,
      lastResult: { received: 10, imported: 2, merged: 0, duplicates: 8, failed: 0 },
    },
  ],
};

describe('SyncRunPanel failed connector retry', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('renders compact connector results and retries only connectors that failed during the completed run', async () => {
    apiMock.mockImplementation(async (path: string, options?: RequestInit) => {
      if (path === '/job-search/sync/current') return snapshot;
      if (path === '/job-search/sync?force=1') {
        expect(options?.method).toBe('POST');
        expect(options?.body).toBe(JSON.stringify({ connectorCodes: ['apec'] }));
        return { job: { id: 'retry-1', status: 'success' } };
      }
      throw new Error(`Unexpected API call: ${path}`);
    });

    const completion = vi.fn();
    window.addEventListener('jobpilot:offers-sync-completed', completion);

    render(<SyncRunPanel />);

    expect(await screen.findByText('0 nouvelles offres · 0 déjà connues · 1 échec')).toBeInTheDocument();
    expect(screen.getByText('2 nouvelles offres · 8 déjà connues')).toBeInTheDocument();

    const progress = screen.getByRole('progressbar', { name: 'Progression de la synchronisation' });
    expect(progress).toHaveAttribute('aria-valuemin', '0');
    expect(progress).toHaveAttribute('aria-valuemax', '100');
    expect(progress).toHaveAttribute('aria-valuenow', '100');
    expect(progress).toHaveAttribute('aria-valuetext', 'Synchronisation terminée');

    fireEvent.click(screen.getByRole('button', { name: 'Réessayer la source en erreur' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith(
      '/job-search/sync?force=1',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ connectorCodes: ['apec'] }),
      }),
    ));
    await waitFor(() => expect(completion).toHaveBeenCalledTimes(1));

    expect(screen.getByText(/Seules les sources en échec seront relancées/)).toBeInTheDocument();
    window.removeEventListener('jobpilot:offers-sync-completed', completion);
  });

  it('renders a partial connector as a warning and does not offer a failed-source retry for it', async () => {
    apiMock.mockResolvedValue({
      ...snapshot,
      connectors: [
        {
          ...snapshot.connectors[0],
          status: 'PARTIAL',
          lastError: 'Une partie des résultats n’a pas pu être importée',
        },
        snapshot.connectors[1],
      ],
    });

    render(<SyncRunPanel />);

    expect(await screen.findByText('Avec avertissement')).toBeInTheDocument();
    expect(screen.queryByText('En erreur')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Réessayer/ })).not.toBeInTheDocument();
  });

  it('does not offer a retry when the run has no connector error', async () => {
    apiMock.mockResolvedValue({
      ...snapshot,
      job: { ...snapshot.job, status: 'success', result: { received: 10, imported: 2, failed: 0 } },
      connectors: snapshot.connectors.map((connector) => ({
        ...connector,
        status: 'READY',
        lastError: null,
      })),
    });

    render(<SyncRunPanel />);

    await screen.findByText('Synchronisation des offres');
    expect(screen.queryByRole('button', { name: /Réessayer/ })).not.toBeInTheDocument();
  });
});
