'use client';

import { Badge } from '@/components/UI';
import { formatCount } from '@/lib/formatCount';

export type ConnectorSyncVisualState = 'waiting' | 'running' | 'success' | 'warning' | 'error';

export type ConnectorSyncResultSummary = {
  received?: number;
  imported?: number;
  merged?: number;
  duplicates?: number;
  profileFiltered?: number;
  failed?: number;
};

export type ConnectorSyncDiagnostics = {
  messagesMatched?: number;
  messagesAlreadyKnown?: number;
  messagesImported?: number;
  offersExtracted?: number;
  messagesFailed?: number;
};

type Props = {
  name: string;
  state: ConnectorSyncVisualState;
  result?: ConnectorSyncResultSummary | null;
  diagnostics?: ConnectorSyncDiagnostics | null;
  error?: string | null;
};

function stateLabel(state: ConnectorSyncVisualState): string {
  if (state === 'waiting') return 'En attente';
  if (state === 'running') return 'En cours';
  if (state === 'success') return 'Terminée';
  if (state === 'warning') return 'Avec avertissement';
  return 'En erreur';
}

function stateTone(state: ConnectorSyncVisualState): 'neutral' | 'blue' | 'good' | 'warn' | 'bad' {
  if (state === 'running') return 'blue';
  if (state === 'success') return 'good';
  if (state === 'warning') return 'warn';
  if (state === 'error') return 'bad';
  return 'neutral';
}

function compactSummary(result: ConnectorSyncResultSummary | null | undefined): string {
  if (!result) return 'Aucun résultat disponible';

  const imported = result.imported ?? 0;
  const duplicates = result.duplicates ?? 0;
  const filtered = result.profileFiltered ?? 0;
  const failed = result.failed ?? 0;
  const parts = [
    formatCount(imported, 'nouvelle offre', 'nouvelles offres'),
    formatCount(duplicates, 'déjà connue', 'déjà connues'),
  ];

  if (filtered > 0) parts.push(formatCount(filtered, 'hors profil', 'hors profil'));
  if (failed > 0) parts.push(formatCount(failed, 'échec', 'échecs'));

  return parts.join(' · ');
}

export function ConnectorSyncResultRow({ name, state, result, diagnostics, error }: Props) {
  const hasDetails = Boolean(
    error
    || diagnostics
    || (result && ((result.received ?? 0) > 0 || (result.merged ?? 0) > 0 || (result.profileFiltered ?? 0) > 0 || (result.failed ?? 0) > 0)),
  );

  return (
    <div className="notice" data-testid="connector-sync-result-row">
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}>
        <div className="actions" style={{ alignItems: 'center' }}>
          <strong>{name}</strong>
          <Badge tone={stateTone(state)}>{stateLabel(state)}</Badge>
        </div>
        <div className="small muted">{compactSummary(result)}</div>
      </div>

      {state === 'running' && (
        <div className="small muted" style={{ marginTop: 6 }}>
          Récupération, normalisation et import en cours…
        </div>
      )}

      {hasDetails && (
        <details style={{ marginTop: 8 }}>
          <summary className="small" style={{ cursor: 'pointer' }}>Voir le détail</summary>
          <div className="small muted" style={{ marginTop: 8 }}>
            {result && (
              <div className="actions small">
                <span>{formatCount(result.received ?? 0, 'offre récupérée', 'offres récupérées')}</span>
                <span>{formatCount(result.imported ?? 0, 'nouvelle offre', 'nouvelles offres')}</span>
                <span>{formatCount(result.merged ?? 0, 'source fusionnée', 'sources fusionnées')}</span>
                <span>{formatCount(result.duplicates ?? 0, 'offre déjà connue', 'offres déjà connues')}</span>
                {(result.profileFiltered ?? 0) > 0 && <span>{formatCount(result.profileFiltered ?? 0, 'offre hors profil', 'offres hors profil')}</span>}
                {(result.failed ?? 0) > 0 && <span>{formatCount(result.failed ?? 0, 'échec', 'échecs')}</span>}
              </div>
            )}

            {diagnostics && (
              <div style={{ marginTop: 8 }}>
                <strong>Diagnostic Gmail :</strong>{' '}
                {formatCount(diagnostics.messagesMatched ?? 0, 'email trouvé', 'emails trouvés')} ·{' '}
                {formatCount(diagnostics.messagesAlreadyKnown ?? 0, 'déjà traité', 'déjà traités')} ·{' '}
                {formatCount(diagnostics.messagesImported ?? 0, 'email importé', 'emails importés')} ·{' '}
                {formatCount(diagnostics.offersExtracted ?? 0, 'offre extraite', 'offres extraites')}
                {(diagnostics.messagesFailed ?? 0) > 0 && ` · ${formatCount(diagnostics.messagesFailed ?? 0, 'email en échec', 'emails en échec')}`}
              </div>
            )}

            {error && <div style={{ marginTop: 8 }}><strong>Erreur :</strong> {error}</div>}
          </div>
        </details>
      )}
    </div>
  );
}
