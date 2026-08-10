'use client';

import { useCallback, useEffect, useState } from 'react';

import { Badge, Card, ErrorBox, Loading } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

export type ConnectorDeadLetter = {
  id: number;
  connectorCode: string;
  stage: 'SEARCH' | 'IMPORT';
  fingerprint: string;
  state: 'TRACKING' | 'OPEN' | 'RESOLVED';
  failureCount: number;
  externalId?: string | null;
  sourceUrl?: string | null;
  title?: string | null;
  errorClass: string;
  errorMessage: string;
  firstFailedAt: string;
  lastFailedAt: string;
  resolvedAt?: string | null;
};

function formatDate(value: string): string {
  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value));
}

function stageLabel(stage: ConnectorDeadLetter['stage']): string {
  return stage === 'SEARCH' ? 'Collecte' : 'Import';
}

function incidentLabel(entry: ConnectorDeadLetter): string {
  return entry.title?.trim()
    || entry.externalId?.trim()
    || (entry.stage === 'SEARCH' ? 'Échec de collecte du connecteur' : 'Offre non identifiable');
}

export function ConnectorDeadLettersSection() {
  const [entries, setEntries] = useState<ConnectorDeadLetter[] | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const load = useCallback(async (): Promise<void> => {
    try {
      const result = await api<ConnectorDeadLetter[]>('/connectors/dead-letters?state=OPEN&limit=50');
      setEntries(result);
      setError('');
    } catch (caughtError: unknown) {
      setEntries([]);
      setError(getErrorMessage(caughtError));
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const resolve = async (entry: ConnectorDeadLetter): Promise<void> => {
    setBusyId(entry.id);
    setError('');
    setMessage('');

    try {
      await api(`/connectors/dead-letters/${entry.id}/resolve`, { method: 'POST' });
      setMessage(`Incident ${entry.connectorCode} marqué comme résolu.`);
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setBusyId(null);
    }
  };

  if (entries === null) {
    return (
      <section aria-labelledby="connector-dead-letter-title" style={{ marginTop: 30 }}>
        <h2 className="section-title" id="connector-dead-letter-title">Incidents persistants</h2>
        <Card><Loading /></Card>
      </section>
    );
  }

  if (entries.length === 0 && error === '' && message === '') {
    return null;
  }

  return (
    <section aria-labelledby="connector-dead-letter-title" style={{ marginTop: 30 }}>
      <div className="actions" style={{ alignItems: 'center', justifyContent: 'space-between' }}>
        <div>
          <h2 className="section-title" id="connector-dead-letter-title" style={{ marginBottom: 4 }}>
            Incidents persistants
          </h2>
          <p className="muted" style={{ marginTop: 0 }}>
            Erreurs de collecte ou d’import répétées au moins trois fois. Résoudre un incident ne relance aucune collecte automatiquement.
          </p>
        </div>
        {entries.length > 0 && <Badge tone="bad">{entries.length} ouverte(s)</Badge>}
      </div>

      {error !== '' && <ErrorBox message={error} />}
      {message !== '' && <div className="success-box" role="status">{message}</div>}

      {entries.length > 0 && (
        <Card>
          <div className="stack" data-testid="connector-dead-letter-list">
            {entries.map((entry) => (
              <div className="list-row" key={entry.id} style={{ alignItems: 'flex-start' }}>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div className="actions" style={{ marginBottom: 7 }}>
                    <Badge tone="bad">OPEN</Badge>
                    <Badge tone="warn">{stageLabel(entry.stage)}</Badge>
                    <Badge>{entry.failureCount} échec(s)</Badge>
                    <Badge><code>{entry.connectorCode}</code></Badge>
                  </div>

                  <strong style={{ display: 'block', marginBottom: 5 }}>{incidentLabel(entry)}</strong>
                  <div className="muted small" style={{ marginBottom: 6 }}>
                    Dernier échec : {formatDate(entry.lastFailedAt)}
                    {entry.externalId && <> · ID : <code>{entry.externalId}</code></>}
                  </div>
                  <p className="small" style={{ marginBottom: entry.sourceUrl ? 7 : 0 }}>
                    {entry.errorMessage}
                  </p>
                  {entry.sourceUrl && (
                    <a href={entry.sourceUrl} target="_blank" rel="noreferrer" className="small">
                      Ouvrir la fiche source
                    </a>
                  )}
                </div>

                <button
                  className="btn secondary small"
                  type="button"
                  disabled={busyId !== null}
                  onClick={() => void resolve(entry)}
                >
                  {busyId === entry.id ? 'Résolution…' : 'Marquer résolu'}
                </button>
              </div>
            ))}
          </div>
        </Card>
      )}
    </section>
  );
}
