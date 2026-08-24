import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { OffersSelectiveSyncPanel } from '@/components/OffersSelectiveSyncPanel';
import type { SourceConnector } from '@/lib/types';

const apiMock = vi.fn();
const pathnameMock = vi.fn(() => '/offres');

vi.mock('next/navigation', () => ({
  usePathname: () => pathnameMock(),
}));

vi.mock('@/lib/api', () => ({
  api: (...args: unknown[]) => apiMock(...args),
}));

function connector(code: string, name: string): SourceConnector {
  return {
    id: 1,
    code,
    name,
    mode: 'API',
    enabled: true,
    configured: true,
    configurationMessage: null,
    collectionAllowed: true,
    policy: {
      complianceStatus: 'ALLOWED',
      complianceLabel: 'Autorisé',
      collectionAllowed: true,
      reviewedAt: null,
      note: null,
      maxRequestsPerSync: null,
      dailyQuota: null,
      minimumDelayMilliseconds: 0,
      respectsRobotsTxt: true,
    },
    parserVersion: null,
    health: {
      status: 'HEALTHY',
      label: 'Sain',
      alert: false,
      sampleSize: 1,
      consecutiveZeroRuns: 0,
      lastExtractionRate: 100,
      baselineAverageReceived: 10,
      reasons: [],
    },
    fieldQuality: {
      received: 0,
      requiredCompleteness: null,
      recommendedCompleteness: null,
      overallCompleteness: null,
      missingRequiredRecords: 0,
      fields: {},
      warnings: [],
    },
    status: 'READY',
    lastSyncedAt: null,
    lastSuccessfulAt: null,
    nextSyncAt: null,
    due: true,
    lastResult: { received: 0, imported: 0, merged: 0, duplicates: 0, failed: 0 },
    lastError: null,
    updatedAt: '2026-08-24T12:00:00Z',
  };
}

const connectors = [connector('apec', 'Apec'), connector('adzuna', 'Adzuna')];

describe('OffersSelectiveSyncPanel', () => {
  beforeEach(() => {
    apiMock.mockReset();
    pathnameMock.mockReturnValue('/offres');
    window.localStorage.clear();
  });

  it('loads connectors only on the offers workspace', async () => {
    apiMock.mockResolvedValue(connectors);
    const { rerender } = render(<OffersSelectiveSyncPanel />);

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/connectors'));
    expect(await screen.findByRole('button', { name: 'Choisir les connecteurs' })).toBeInTheDocument();

    apiMock.mockClear();
    pathnameMock.mockReturnValue('/candidatures');
    rerender(<OffersSelectiveSyncPanel />);

    expect(screen.queryByText('Synchroniser les sources')).not.toBeInTheDocument();
    expect(apiMock).not.toHaveBeenCalled();
  });

  it('posts the exact selected connector codes and announces completion', async () => {
    apiMock.mockImplementation(async (path: string, options?: RequestInit) => {
      if (path === '/connectors') return connectors;
      if (path === '/job-search/sync?force=1') {
        expect(options?.method).toBe('POST');
        expect(options?.body).toBe(JSON.stringify({ connectorCodes: ['apec'] }));
        return { job: { id: 'run-1', status: 'success' } };
      }
      throw new Error(`Unexpected API call: ${path}`);
    });
    const completion = vi.fn();
    window.addEventListener('jobpilot:offers-sync-completed', completion);

    render(<OffersSelectiveSyncPanel />);
    fireEvent.click(await screen.findByRole('button', { name: 'Choisir les connecteurs' }));
    fireEvent.click(screen.getByRole('checkbox', { name: 'Synchroniser Adzuna' }));
    fireEvent.click(screen.getByRole('button', { name: 'Synchroniser 1 connecteur' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith(
      '/job-search/sync?force=1',
      expect.objectContaining({ method: 'POST', body: JSON.stringify({ connectorCodes: ['apec'] }) }),
    ));
    await waitFor(() => expect(completion).toHaveBeenCalledTimes(1));

    window.removeEventListener('jobpilot:offers-sync-completed', completion);
  });

  it('keeps the quick action as a force-sync without a selection payload', async () => {
    apiMock.mockImplementation(async (path: string, options?: RequestInit) => {
      if (path === '/connectors') return connectors;
      if (path === '/job-search/sync?force=1') {
        expect(options).toEqual({ method: 'POST' });
        return { job: { id: 'run-2', status: 'success' } };
      }
      throw new Error(`Unexpected API call: ${path}`);
    });

    render(<OffersSelectiveSyncPanel />);
    fireEvent.click(await screen.findByRole('button', { name: 'Tout synchroniser' }));

    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/job-search/sync?force=1', { method: 'POST' }));
  });
});
