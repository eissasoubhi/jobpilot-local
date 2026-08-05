'use client';

import { useCallback, useEffect, useState } from 'react';

import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { ConnectorSyncRun, SourceConnector } from '@/lib/types';

function formatDate(value: string | null | undefined): string {
  if (!value) return 'Jamais';

  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value));
}

function formatReviewDate(value: string | null | undefined): string {
  if (!value) return 'Non revue';

  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(new Date(`${value}T12:00:00`));
}

function duration(value: number | null | undefined): string {
  if (value == null) return '—';
  if (value < 1000) return `${value} ms`;

  return `${(value / 1000).toFixed(1)} s`;
}

function percentage(value: number | null | undefined): string {
  if (value == null) return '—';

  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(value)} %`;
}

function modeLabel(mode: SourceConnector['mode']): string {
  return {
    API: 'API',
    RSS: 'Flux RSS',
    SCRAPING_HTTP: 'Scraping HTTP',
    SCRAPING_BROWSER: 'Scraping navigateur',
    GMAIL: 'Gmail',
    EXTENSION: 'Extension Chrome',
    MANUAL: 'Import manuel',
  }[mode];
}

function statusTone(status: string): 'good' | 'warn' | 'bad' | 'blue' | 'neutral' {
  if (status === 'SUCCESS' || status === 'SUCCEEDED' || status === 'READY') return 'good';
  if (status === 'RUNNING') return 'blue';
  if (status === 'PARTIAL' || status === 'MISCONFIGURED' || status === 'NEVER_SYNCED') return 'warn';
  if (status === 'ERROR' || status === 'FAILED' || status === 'COMPLIANCE_BLOCKED') return 'bad';
  return 'neutral';
}

function healthTone(status: SourceConnector['health']['status']): 'good' | 'warn' | 'bad' | 'blue' | 'neutral' {
  if (status === 'HEALTHY') return 'good';
  if (status === 'WATCH') return 'blue';
  if (status === 'DEGRADED') return 'warn';
  if (status === 'BROKEN') return 'bad';
  return 'neutral';
}

function complianceTone(status: SourceConnector['policy']['complianceStatus']): 'good' | 'warn' | 'bad' | 'blue' | 'neutral' {
  if (status === 'ALLOWED') return 'good';
  if (status === 'AUTHORIZED_ONLY') return 'blue';
  if (status === 'EMAIL_OR_EXTENSION_ONLY' || status === 'UNDER_REVIEW') return 'warn';
  return 'bad';
}

export default function ConnectorsPage() {
  const [connectors, setConnectors] = useState<SourceConnector[] | null>(null);
  const [history, setHistory] = useState<ConnectorSyncRun[] | null>(null);
  const [busyCode, setBusyCode] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const load = useCallback(async (): Promise<void> => {
    try {
      const [connectorItems, runItems] = await Promise.all([
        api<SourceConnector[]>('/connectors'),
        api<ConnectorSyncRun[]>('/connectors/history?limit=20'),
      ]);
      setConnectors(connectorItems);
      setHistory(runItems);
      setError('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const toggle = async (connector: SourceConnector): Promise<void> => {
    setBusyCode(connector.code);
    setMessage('');

    try {
      await api(`/connectors/${encodeURIComponent(connector.code)}`, {
        method: 'PATCH',
        body: JSON.stringify({ enabled: !connector.enabled }),
      });
      setMessage(`${connector.name} est maintenant ${connector.enabled ? 'désactivé' : 'activé'}.`);
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setBusyCode('');
    }
  };

  const synchronize = async (connector: SourceConnector): Promise<void> => {
    setBusyCode(connector.code);
    setMessage('');

    try {
      const result = await api<{ message?: string }>(`/connectors/${encodeURIComponent(connector.code)}/sync`, {
        method: 'POST',
      });
      setMessage(result.message ?? `Synchronisation de ${connector.name} terminée.`);
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setBusyCode('');
    }
  };

  return (
    <>
      <PageHeader
        title="Connecteurs"
        description="État, autorisation, santé d’extraction, limites et historique des sources d’offres de JobPilot."
        actions={
          <button className="btn secondary" type="button" onClick={() => void load()}>
            Actualiser
          </button>
        }
      />

      {error !== '' && <ErrorBox message={error} />}
      {message !== '' && <div className="success-box" role="status">{message}</div>}

      {connectors === null ? (
        <Loading />
      ) : connectors.length === 0 ? (
        <Card><Empty>Aucun connecteur n’est enregistré.</Empty></Card>
      ) : (
        <div className="stack">
          {connectors.map((connector) => (
            <Card key={connector.code}>
              <div className="list-row" style={{ alignItems: 'flex-start', paddingTop: 0, paddingBottom: 0 }}>
                <div style={{ flex: 1 }}>
                  <div className="actions" style={{ marginBottom: 8 }}>
                    <Badge tone={statusTone(connector.status)}>{connector.status}</Badge>
                    <Badge tone={healthTone(connector.health.status)}>{connector.health.label}</Badge>
                    <Badge tone="blue">{modeLabel(connector.mode)}</Badge>
                    <Badge tone={complianceTone(connector.policy.complianceStatus)}>
                      {connector.policy.complianceLabel}
                    </Badge>
                    <Badge tone={connector.enabled ? 'good' : 'neutral'}>
                      {connector.enabled ? 'Activé' : 'Désactivé'}
                    </Badge>
                    <Badge tone={connector.configured ? 'good' : 'warn'}>
                      {connector.configured ? 'Configuré' : 'Configuration requise'}
                    </Badge>
                  </div>

                  <h3>{connector.name}</h3>
                  <div className="muted small">
                    Code : <code>{connector.code}</code>
                    {connector.parserVersion && <> · Parseur : <code>{connector.parserVersion}</code></>}
                  </div>

                  {connector.configurationMessage && (
                    <p className="small" style={{ marginBottom: 0 }}>{connector.configurationMessage}</p>
                  )}
                  {connector.policy.note && (
                    <p className="small" style={{ marginBottom: 0 }}>
                      <strong>Politique de collecte :</strong> {connector.policy.note}
                    </p>
                  )}
                  {connector.health.reasons[0] && (
                    <p className="small" style={{ marginBottom: 0 }}>
                      <strong>Santé d’extraction :</strong> {connector.health.reasons[0]}
                    </p>
                  )}
                  {connector.lastError && <ErrorBox message={connector.lastError} />}

                  <div className="actions" style={{ marginTop: 12 }}>
                    <Badge>Revue : {formatReviewDate(connector.policy.reviewedAt)}</Badge>
                    {connector.policy.maxRequestsPerSync != null && (
                      <Badge>{connector.policy.maxRequestsPerSync} requête(s) max/sync</Badge>
                    )}
                    {connector.policy.dailyQuota != null && (
                      <Badge>{connector.policy.dailyQuota} requête(s) max/jour</Badge>
                    )}
                    {connector.policy.minimumDelayMilliseconds > 0 && (
                      <Badge>Délai min. {duration(connector.policy.minimumDelayMilliseconds)}</Badge>
                    )}
                    {connector.policy.respectsRobotsTxt && <Badge>robots.txt respecté</Badge>}
                  </div>

                  <div className="actions" style={{ marginTop: 12 }}>
                    <Badge>Référence : {connector.health.sampleSize} sync(s)</Badge>
                    <Badge>Taux récent : {percentage(connector.health.lastExtractionRate)}</Badge>
                    {connector.health.baselineAverageReceived != null && (
                      <Badge>Moyenne positive : {connector.health.baselineAverageReceived} offre(s)</Badge>
                    )}
                    {connector.health.consecutiveZeroRuns > 0 && (
                      <Badge tone={connector.health.alert ? 'warn' : 'neutral'}>
                        {connector.health.consecutiveZeroRuns} sync(s) vide(s)
                      </Badge>
                    )}
                  </div>

                  <div className="actions" style={{ marginTop: 12 }}>
                    <Badge>Dernière sync : {formatDate(connector.lastSyncedAt)}</Badge>
                    <Badge>Prochaine : {connector.enabled && connector.configured && connector.collectionAllowed ? formatDate(connector.nextSyncAt) : 'non planifiée'}</Badge>
                    <Badge tone="good">{connector.lastResult.imported} nouvelle(s)</Badge>
                    <Badge tone="blue">{connector.lastResult.merged} source(s) fusionnée(s)</Badge>
                    <Badge>{connector.lastResult.duplicates} occurrence(s) connue(s)</Badge>
                    {connector.lastResult.failed > 0 && <Badge tone="warn">{connector.lastResult.failed} échec(s)</Badge>}
                  </div>

                  <div className="actions" style={{ marginTop: 14 }}>
                    <button
                      className="btn secondary small"
                      type="button"
                      disabled={busyCode !== ''}
                      onClick={() => void toggle(connector)}
                    >
                      {connector.enabled ? 'Désactiver' : 'Activer'}
                    </button>
                    <button
                      className="btn small"
                      type="button"
                      disabled={busyCode !== '' || !connector.enabled || !connector.configured || !connector.collectionAllowed}
                      onClick={() => void synchronize(connector)}
                    >
                      {busyCode === connector.code ? 'Synchronisation…' : 'Tester maintenant'}
                    </button>
                  </div>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}

      <h2 className="section-title" style={{ marginTop: 30 }}>Historique récent</h2>
      <p className="muted" style={{ marginTop: -6 }}>Les vingt dernières exécutions, manuelles ou planifiées.</p>
      <Card>
        {history === null ? (
          <Loading />
        ) : history.length === 0 ? (
          <Empty>Aucune synchronisation enregistrée pour le moment.</Empty>
        ) : (
          history.map((run) => (
            <div className="list-row" key={run.id}>
              <div style={{ flex: 1 }}>
                <div className="actions" style={{ marginBottom: 6 }}>
                  <Badge tone={statusTone(run.status)}>{run.status}</Badge>
                  <Badge>{run.trigger}</Badge>
                  <Badge>{duration(run.durationMs)}</Badge>
                  {run.details.parserVersion && <Badge>Parseur {run.details.parserVersion}</Badge>}
                  {run.details.normalizationRate != null && (
                    <Badge>Taux {percentage(run.details.normalizationRate)}</Badge>
                  )}
                  {run.details.zeroResults && <Badge tone="warn">Aucun résultat</Badge>}
                </div>
                <h3>{run.connector.name}</h3>
                <div className="muted small">{formatDate(run.startedAt)}</div>
                <div className="actions" style={{ marginTop: 9 }}>
                  <Badge>{run.received} reçue(s)</Badge>
                  <Badge tone="good">{run.imported} nouvelle(s)</Badge>
                  <Badge tone="blue">{run.merged} source(s) fusionnée(s)</Badge>
                  <Badge>{run.duplicates} occurrence(s) connue(s)</Badge>
                  {run.failed > 0 && <Badge tone="warn">{run.failed} échec(s)</Badge>}
                </div>
                {run.error && <p className="small" style={{ marginBottom: 0 }}>{run.error}</p>}
              </div>
            </div>
          ))
        )}
      </Card>
    </>
  );
}
