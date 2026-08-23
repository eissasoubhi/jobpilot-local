'use client';

import { usePathname } from 'next/navigation';
import { useEffect, useMemo, useState } from 'react';

import { Badge, Card } from '@/components/UI';
import { api } from '@/lib/api';

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
  lastResult?: {
    received?: number;
    imported?: number;
    merged?: number;
    duplicates?: number;
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

function isTerminal(status: SyncStatus): boolean {
  return status === 'success' || status === 'partial' || status === 'failed';
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

function connectorState(connector: ConnectorSnapshot, job: SyncJob): 'running' | 'done' | 'error' | 'waiting' {
  if (connector.status === 'RUNNING') return 'running';
  if (happenedDuringRun(connector.lastSyncedAt, job.startedAt)) {
    return connector.status === 'ERROR' || connector.status === 'PARTIAL' ? 'error' : 'done';
  }
  return 'waiting';
}

function connectorLabel(state: ReturnType<typeof connectorState>): string {
  if (state === 'running') return 'Recherche et import en cours';
  if (state === 'done') return 'Terminé';
  if (state === 'error') return 'Terminé avec erreur';
  return 'En attente';
}

export function SyncRunPanel() {
  const pathname = usePathname();
  const [snapshot, setSnapshot] = useState<SyncSnapshot | null>(null);
  const [now, setNow] = useState(() => Date.now());

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
  const completed = states.filter(({ state }) => state === 'done' || state === 'error').length;
  const current = states.find(({ state }) => state === 'running')?.connector;
  const terminal = isTerminal(job.status);
  const workerUnavailable = job.status === 'queued' && worker.status !== 'active';
  const progress = terminal ? 100 : connectors.length > 0 ? Math.round((completed / connectors.length) * 100) : 0;
  const statusLabel = job.status === 'queued'
    ? 'Mise en file'
    : job.status === 'running'
      ? 'Worker actif'
      : job.status === 'success'
        ? 'Terminée'
        : job.status === 'partial'
          ? 'Terminée partiellement'
          : 'Échec';

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
        aria-label={`Progression de la synchronisation ${progress} %`}
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
            const result = connector.lastResult ?? {};
            const showResult = happenedDuringRun(connector.lastSyncedAt, job.startedAt);
            return (
              <div className="notice" key={connector.code}>
                <div className="actions" style={{ alignItems: 'center' }}>
                  <strong>{connector.name}</strong>
                  <Badge tone={state === 'running' ? 'blue' : state === 'done' ? 'good' : state === 'error' ? 'warn' : 'neutral'}>
                    {connectorLabel(state)}
                  </Badge>
                </div>
                {state === 'running' && (
                  <div className="small muted" style={{ marginTop: 6 }}>
                    Connexion à la source, récupération puis normalisation/import des offres…
                  </div>
                )}
                {showResult && (
                  <div className="actions small" style={{ marginTop: 7 }}>
                    <span>{result.received ?? 0} reçue(s)</span>
                    <span>{result.imported ?? 0} nouvelle(s)</span>
                    <span>{result.merged ?? 0} fusionnée(s)</span>
                    <span>{result.duplicates ?? 0} connue(s)</span>
                    {(result.failed ?? 0) > 0 && <span>{result.failed} échec(s)</span>}
                  </div>
                )}
                {state === 'error' && connector.lastError && (
                  <div className="small" style={{ marginTop: 6 }}>{connector.lastError}</div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {job.result && terminal && (
        <div className="actions" style={{ marginTop: 14 }}>
          {job.result.received != null && <Badge>{job.result.received} reçue(s)</Badge>}
          {job.result.imported != null && <Badge tone="good">{job.result.imported} nouvelle(s)</Badge>}
          {job.result.merged != null && <Badge tone="blue">{job.result.merged} fusionnée(s)</Badge>}
          {job.result.duplicates != null && <Badge>{job.result.duplicates} connue(s)</Badge>}
          {job.result.profileFiltered != null && <Badge>{job.result.profileFiltered} hors profil</Badge>}
          {(job.result.failed ?? 0) > 0 && <Badge tone="warn">{job.result.failed} échec(s)</Badge>}
        </div>
      )}
    </Card>
  );
}
