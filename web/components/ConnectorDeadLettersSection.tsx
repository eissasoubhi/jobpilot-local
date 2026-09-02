'use client';

import { useCallback, useEffect, useState } from 'react';

import styles from './ConnectorDeadLettersSection.module.css';
import { Skeleton, SkeletonGroup } from '@/components/Skeleton';
import { Badge, Button, Card, DataList, DataListItem, DataToolbar, ErrorBox, InlineFeedback } from '@/components/UI';
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

function ConnectorDeadLettersSkeleton() {
  return (
    <Card>
      <SkeletonGroup label="Chargement des incidents persistants" className={styles.skeletonList}>
        {[0, 1].map((item) => (
          <div className={styles.skeletonItem} key={item} aria-hidden="true">
            <div className="actions">
              <Skeleton width={62} height={24} />
              <Skeleton width={78} height={24} />
              <Skeleton width={86} height={24} />
            </div>
            <Skeleton width="48%" height={20} />
            <Skeleton width="70%" />
            <Skeleton width="92%" height={30} />
          </div>
        ))}
      </SkeletonGroup>
    </Card>
  );
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
      <section aria-labelledby="connector-dead-letter-title" className={styles.section}>
        <h2 className="section-title" id="connector-dead-letter-title">Incidents persistants</h2>
        <ConnectorDeadLettersSkeleton />
      </section>
    );
  }

  if (entries.length === 0 && error === '' && message === '') {
    return null;
  }

  return (
    <section aria-labelledby="connector-dead-letter-title" className={styles.section}>
      <DataToolbar
        actions={entries.length > 0 ? <Badge tone="bad">{entries.length} ouverte(s)</Badge> : undefined}
      >
        <h2 className={`section-title ${styles.toolbarTitle}`} id="connector-dead-letter-title">
          Incidents persistants
        </h2>
        <p className={`muted ${styles.toolbarDescription}`}>
          Erreurs de collecte ou d’import répétées au moins trois fois. Résoudre un incident ne relance aucune collecte automatiquement.
        </p>
      </DataToolbar>

      {error !== '' && <ErrorBox message={error} />}
      {message !== '' && <InlineFeedback tone="success">{message}</InlineFeedback>}

      {entries.length > 0 && (
        <Card>
          <DataList aria-label="Incidents persistants des connecteurs" data-testid="connector-dead-letter-list">
            {entries.map((entry) => (
              <DataListItem key={entry.id}>
                <div className={styles.itemContent}>
                  <div className={`actions ${styles.badges}`}>
                    <Badge tone="bad">OPEN</Badge>
                    <Badge tone="warn">{stageLabel(entry.stage)}</Badge>
                    <Badge>{entry.failureCount} échec(s)</Badge>
                    <Badge><code>{entry.connectorCode}</code></Badge>
                  </div>

                  <strong className={styles.incidentTitle}>{incidentLabel(entry)}</strong>
                  <div className={`muted small ${styles.metadata}`}>
                    Dernier échec : {formatDate(entry.lastFailedAt)}
                    {entry.externalId && <> · ID : <code>{entry.externalId}</code></>}
                  </div>
                  <p className={`small ${styles.errorMessage}`}>
                    {entry.errorMessage}
                  </p>
                  {entry.sourceUrl && (
                    <a href={entry.sourceUrl} target="_blank" rel="noreferrer" className={`small ${styles.sourceLink}`}>
                      Ouvrir la fiche source
                    </a>
                  )}
                </div>

                <Button
                  className={styles.resolveAction}
                  variant="secondary"
                  size="small"
                  disabled={busyId !== null}
                  loading={busyId === entry.id}
                  onClick={() => void resolve(entry)}
                >
                  {busyId === entry.id ? 'Résolution…' : 'Marquer résolu'}
                </Button>
              </DataListItem>
            ))}
          </DataList>
        </Card>
      )}
    </section>
  );
}
