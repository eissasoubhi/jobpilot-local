'use client';

import { usePathname } from 'next/navigation';
import { useEffect, useMemo, useState } from 'react';

import { ConnectorSyncResultRow, type ConnectorSyncVisualState } from '@/components/ConnectorSyncResultRow';
import { Badge, Card, ErrorBox } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import { formatCount } from '@/lib/formatCount';

type SyncStatus = 'queued' | 'running' | 'success' | 'partial' | 'failed';

type SyncJob = {
  id: string;
  status: SyncStatus;
  queuedAt: string;
  startedAt: string | null;
  finishedAt: string | null;
  updatedAt: string;
  result: {
    message?: string;
    received?: number;
    imported?: number;
    merged?: number;
    duplicates?: number;
    profileFiltered?: number;
    failed?: number;
  } | null;
  error?: { code?: string; message?: string } | null;
};

type SearchDiagnostics = {
  source?: string;
  messagesMatched?: number;
  messagesImported?: number;
  messagesAlreadyKnown?: number;
  messagesFailed?: number;
  offersExtracted?: number;
  messagesAssociated?: number;
  messagesActionRequired?: number;
};

type ProfileFilterReasonCounts = {
  score_below_threshold?: number;
  missing_must_have?: number;
  explicit_conflict?: number;
};

type ConnectorSnapshot = {
  code: string;
  name: string;
  enabled?: boolean;
  configured?: boolean;
  collectionAllowed?: boolean;
  status?: string;
  due?: boolean;
  lastSyncedAt?: string | null;
  lastError?: string | null;
  searchDiagnostics?: SearchDiagnostics | null;
  profileFilterReasonCounts?: ProfileFilterReasonCounts | null;
  lastResult?: {
    received?: number;
    imported?: number;
    merged?: number;
    duplicates?: number;
    profileFiltered?: number;
    failed?: number;
  };
};

type WorkerSnapshot = {
  status: 'active' | 'stale' | 'missing';
  updatedAt: string | null;
};

type SyncSnapshot = {
  job: SyncJob | null;
  connectors: ConnectorSnapshot[];
  worker: WorkerSnapshot;
};

type SyncJobResponse = { job: SyncJob };

type ConnectorRunState = 'running' | 'done' | 'warning' | 'error' | 'waiting';

function isTerminal(status: SyncStatus): boolean {
  return status === 'success' || status === 'partial' || status === 'failed';
}

function wait(milliseconds: number): Promise<void> {
  return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

function happenedDuringRun(value: string | null | undefined, startedAt: string | null): boolean {
  if (!value || !startedAt) return false;
  return new Date(value).getTime() >= new Date(startedAt).getTime();
}

function elapsed(job: SyncJob, now: number): string {
  const start = new Date(job.startedAt ?? job.queuedAt).getTime();
  const end = job.finishedAt ? new Date(job.finishedAt).getTime() : now;
  const totalSeconds = Math.max(0, Math.floor((end - start) / 1000));
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

function connectorState(connector: ConnectorSnapshot, job: SyncJob): ConnectorRunState {
  if (connector.status === 'RUNNING') return 'running';
  if (happenedDuringRun(connector.lastSyncedAt, job.startedAt)) {
    if (connector.status === 'ERROR') return 'error';
    if (connector.status === 'PARTIAL') return 'warning';
    return 'done';
  }
  return 'waiting';
}

function connectorVisualState(state: ConnectorRunState): ConnectorSyncVisualState {
  if (state === 'running') return 'running';
  if (state === 'done') return 'success';
  if (state === 'warning') return 'warning';
  if (state === 'error') return 'error';
  return 'waiting';
}

export function SyncRunPanel() {
  const pathname = usePathname();
  const [snapshot, setSnapshot] = useState<SyncSnapshot | null>(null);
  const [now, setNow] = useState(() => Date.now());
  const [retrying, setRetrying] = useState(false);
  const [retryError, setRetryError] = useState('');

  useEffect(() => {
    if (pathname !== '/offres') return;

    let active = true;
    const refresh = async (): Promise<void> => {
      try {
        const next = await api<SyncSnapshot>('/job-search/sync/current');
        if (active) setSnapshot(next);
      } catch {
        // The offers page already owns the main error surface. This panel stays non-blocking.
      }
    };

    void refresh();
    const poll = window.setInterval(() => void refresh(), 1500);
    const clock = window.setInterval(() => setNow(Date.now()), 1000);

    return () => {
      active = false;
      window.clearInterval(poll);
      window.clearInterval(clock);
    };
  }, [pathname]);

  const job = snapshot?.job ?? null;
  const worker = snapshot?.worker ?? { status: 'missing' as const, updatedAt: null };
  const connectors = useMemo(
    () => (snapshot?.connectors ?? []).filter((connector) => (
      connector.enabled !== false
      && connector.configured !== false
      && connector.collectionAllowed !== false
    )),
    [snapshot],
  );

  if (pathname !== '/offres' || job === null) return null;

  const states = connectors.map((connector) => ({ connector, state: connectorState(connector, job) }));
  const completed = states.filter(({ state }) => state === 'done' || state === 'warning' || state === 'error').length;
  const current = states.find(({ state }) => state === 'running')?.connector;
  const terminal = isTerminal(job.status);
  const failedConnectorCodes = terminal
    ? states.filter(({ state }) => state === 'error').map(({ connector }) => connector.code)
    : [];
  const workerUnavailable = job.status === 'queued' && worker.status !== 'active';
  const progress = terminal ? 100 : connectors.length > 0 ? Math.round((completed / connectors.length) * 100) : 0;
  const progressText = terminal
    ? 'Synchronisation terminée'
    : `${completed} sur ${connectors.length || 0} sources terminées`;
  const statusLabel = job.status === 'queued'
    ? 'Mise en file'
    : job.status === 'running'
      ? 'Worker actif'
      : job.status === 'success'
        ? 'Terminée'
        : job.status === 'partial'
          ? 'Terminée partiellement'
          : 'Échec';

  const retryFailedConnectors = async (): Promise<void> => {
    if (retrying || failedConnectorCodes.length === 0) return;

    setRetrying(true);
    setRetryError('');

    try {
      let response = await api<SyncJobResponse>('/job-search/sync?force=1', {
        method: 'POST',
        body: JSON.stringify({ connectorCodes: failedConnectorCodes }),
      });

      while (!isTerminal(response.job.status)) {
        await wait(1000);
        response = await api<SyncJobResponse>(`/job-search/sync/${encodeURIComponent(response.job.id)}`);
      }

      if (response.job.status === 'failed') {
        throw new Error(response.job.error?.message ?? 'La nouvelle tentative a échoué.');
      }

      const next = await api<SyncSnapshot>('/job-search/sync/current');
      setSnapshot(next);
      window.dispatchEvent(new CustomEvent('jobpilot:offers-sync-completed', {
        detail: { connectorCodes: failedConnectorCodes, status: response.job.status },
      }));
    } catch (caughtError: unknown) {
      setRetryError(getErrorMessage(caughtError));
    } finally {
      setRetrying(false);
    }
  };

  return (
    <Card>
      <div style={{ display: 'flex', justifyContent: 'space-between', gap: 16, alignItems: 'flex-start', flexWrap: 'wrap' }}>
        <div style={{ flex: 1, minWidth: 260 }}>
          <div className="actions" style={{ alignItems: 'center' }}>
            <strong>Synchronisation des offres</strong>
            <Badge tone={job.status === 'failed' ? 'bad' : job.status === 'partial' ? 'warn' : terminal ? 'good' : 'blue'}>
              {statusLabel}
            </Badge>
            {job.status === 'queued' && (
              <Badge tone={worker.status === 'active' ? 'good' : 'warn'}>
                {worker.status === 'active' ? 'Worker prêt' : 'Worker indisponible'}
              </Badge>
            )}
            <Badge>{elapsed(job, now)}</Badge>
          </div>
          <div className="small muted" style={{ marginTop: 7 }}>
            {workerUnavailable
              ? 'Le worker asynchrone n’est pas détecté. Redémarre JobPilot pour recréer le scheduler avec la version courante.'
              : job.status === 'queued'
                ? 'La demande est acceptée. Le worker doit maintenant la prendre en charge.'
                : current
                  ? `Source active : ${current.name}`
                  : job.error?.message ?? job.result?.message ?? 'Suivi du run en temps réel.'}
          </div>
        </div>
        <div className="small muted">
          {terminal ? '100 %' : `${completed}/${connectors.length || 0} sources`} · activité {new Date(job.updatedAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
        </div>
      </div>

      <div
        role="progressbar"
        aria-label="Progression de la synchronisation"
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={progress}
        aria-valuetext={progressText}
        style={{ height: 8, borderRadius: 999, background: 'var(--surface-muted, #e5e7eb)', overflow: 'hidden', marginTop: 14 }}
      >
        <div style={{ height: '100%', width: `${progress}%`, background: 'currentColor', transition: 'width 200ms ease' }} />
      </div>

      {workerUnavailable && (
        <div className="notice" style={{ marginTop: 14 }}>
          <strong>Scheduler à redémarrer</strong>
          <div className="small" style={{ marginTop: 5 }}>
            Le conteneur peut être encore actif avec une ancienne commande. Relance JobPilot, puis relance la recherche ; tes offres locales restent disponibles.
          </div>
        </div>
      )}

      {job.error?.message && (
        <div className="notice" style={{ marginTop: 14 }}>
          <strong>Synchronisation interrompue</strong>
          <div className="small" style={{ marginTop: 5 }}>{job.error.message}</div>
        </div>
      )}

      {states.length > 0 && (
        <div className="stack" style={{ gap: 8, marginTop: 14 }}>
          {states.map(({ connector, state }) => {
            const showResult = happenedDuringRun(connector.lastSyncedAt, job.startedAt);
            const gmailDiagnostics = connector.code === 'gmail' && connector.searchDiagnostics?.source === 'gmail'
              ? connector.searchDiagnostics
              : null;

            return (
              <ConnectorSyncResultRow
                key={connector.code}
                name={connector.name}
                state={connectorVisualState(state)}
                result={showResult ? connector.lastResult : null}
                profileFilterReasonCounts={showResult ? connector.profileFilterReasonCounts : null}
                diagnostics={showResult ? gmailDiagnostics : null}
                error={showResult && state === 'error' ? connector.lastError : null}
              />
            );
          })}
        </div>
      )}

      {job.result && terminal && (
        <div className="actions" style={{ marginTop: 14 }}>
          {job.result.received != null && <Badge>{formatCount(job.result.received, 'offre récupérée', 'offres récupérées')}</Badge>}
          {job.result.imported != null && <Badge tone="good">{formatCount(job.result.imported, 'nouvelle offre', 'nouvelles offres')}</Badge>}
          {job.result.merged != null && <Badge tone="blue">{formatCount(job.result.merged, 'source fusionnée', 'sources fusionnées')}</Badge>}
          {job.result.duplicates != null && <Badge>{formatCount(job.result.duplicates, 'offre déjà connue', 'offres déjà connues')}</Badge>}
          {job.result.profileFiltered != null && <Badge>{formatCount(job.result.profileFiltered, 'offre hors profil', 'offres hors profil')}</Badge>}
          {(job.result.failed ?? 0) > 0 && <Badge tone="warn">{formatCount(job.result.failed ?? 0, 'échec', 'échecs')}</Badge>}
        </div>
      )}

      {failedConnectorCodes.length > 0 && (
        <div style={{ marginTop: 14 }}>
          <button
            type="button"
            className="btn secondary small"
            disabled={retrying}
            onClick={() => void retryFailedConnectors()}
          >
            {retrying
              ? 'Nouvelle tentative…'
              : failedConnectorCodes.length === 1
                ? 'Réessayer la source en erreur'
                : `Réessayer les ${failedConnectorCodes.length} sources en erreur`}
          </button>
          <div className="small muted" style={{ marginTop: 6 }}>
            Seules les sources en échec seront relancées ; les sources déjà terminées ne seront pas rejouées.
          </div>
        </div>
      )}

      {retryError !== '' && <div style={{ marginTop: 12 }}><ErrorBox message={retryError} /></div>}
    </Card>
  );
}
