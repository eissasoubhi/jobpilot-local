'use client';

import { usePathname } from 'next/navigation';
import { useCallback, useEffect, useState } from 'react';

import { SelectiveConnectorSyncPanel } from '@/components/SelectiveConnectorSyncPanel';
import { Card, ErrorBox, Loading } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { SourceConnector } from '@/lib/types';

type SyncStatus = 'queued' | 'running' | 'success' | 'partial' | 'failed';

type SyncJob = {
  id: string;
  status: SyncStatus;
  error?: { message?: string } | null;
};

type SyncJobResponse = { job: SyncJob };

function isTerminal(status: SyncStatus): boolean {
  return status === 'success' || status === 'partial' || status === 'failed';
}

function wait(milliseconds: number): Promise<void> {
  return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

export function OffersSelectiveSyncPanel() {
  const pathname = usePathname();
  const [connectors, setConnectors] = useState<SourceConnector[] | null>(null);
  const [syncing, setSyncing] = useState(false);
  const [error, setError] = useState('');

  const loadConnectors = useCallback(async (): Promise<void> => {
    try {
      const items = await api<SourceConnector[]>('/connectors');
      setConnectors(items);
    } catch (caughtError: unknown) {
      setConnectors((current) => current ?? []);
      setError(`Impossible de charger les connecteurs : ${getErrorMessage(caughtError)}`);
    }
  }, []);

  useEffect(() => {
    if (pathname !== '/offres') return;
    void loadConnectors();
  }, [loadConnectors, pathname]);

  const synchronize = useCallback(async (connectorCodes?: string[]): Promise<void> => {
    setSyncing(true);
    setError('');

    try {
      let response = await api<SyncJobResponse>('/job-search/sync?force=1', {
        method: 'POST',
        ...(connectorCodes
          ? { body: JSON.stringify({ connectorCodes }) }
          : {}),
      });

      while (!isTerminal(response.job.status)) {
        await wait(1000);
        response = await api<SyncJobResponse>(`/job-search/sync/${encodeURIComponent(response.job.id)}`);
      }

      if (response.job.status === 'failed') {
        throw new Error(response.job.error?.message ?? 'La synchronisation a échoué.');
      }

      await loadConnectors();
      window.dispatchEvent(new CustomEvent('jobpilot:offers-sync-completed', {
        detail: { connectorCodes: connectorCodes ?? null, status: response.job.status },
      }));
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSyncing(false);
    }
  }, [loadConnectors]);

  if (pathname !== '/offres') return null;

  return (
    <Card>
      <div style={{ display: 'flex', justifyContent: 'space-between', gap: 16, alignItems: 'flex-start', flexWrap: 'wrap' }}>
        <div>
          <strong>Synchroniser les sources</strong>
          <div className="small muted" style={{ marginTop: 5 }}>
            Lance toutes les sources éligibles ou limite volontairement le prochain run aux connecteurs de ton choix.
          </div>
        </div>
        {connectors === null ? (
          <Loading />
        ) : (
          <SelectiveConnectorSyncPanel
            connectors={connectors}
            syncing={syncing}
            onSynchronize={synchronize}
          />
        )}
      </div>
      {error !== '' && <div style={{ marginTop: 12 }}><ErrorBox message={error} /></div>}
    </Card>
  );
}
