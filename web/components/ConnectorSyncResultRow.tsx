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
  durationMs?: number;
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

function formatDuration(durationMs: number): string {
  const milliseconds = Math.max(0, Math.round(durationMs));
  if (milliseconds < 1000) return `${milliseconds} ms`;

  const totalSeconds = milliseconds / 1000;
  if (totalSeconds < 60) {
    const seconds = totalSeconds < 10
      ? Math.round(totalSeconds * 10) / 10
      : Math.round(totalSeconds);
    return `${String(seconds).replace('.', ',')} s`;
  }

  const minutes = Math.floor(totalSeconds / 60);
  const seconds = Math.round(totalSeconds % 60);
  return seconds === 0 ? `${minutes} min` : `${minutes} min ${seconds} s`;
}

function zeroResultExplanation(
  state: ConnectorSyncVisualState,
  result: ConnectorSyncResultSummary | null | undefined,
  diagnostics: ConnectorSyncDiagnostics | null | undefined,
  error: string | null | undefined,
): string | null {
  if (
    !result
    || error
    || state === 'waiting'
    || state === 'running'
    || state === 'error'
    || (result.received ?? 0) > 0
    || (result.failed ?? 0) > 0
  ) {
    return null;
  }

  if (diagnostics) {
    const matched = diagnostics.messagesMatched ?? 0;
    const extracted = diagnostics.offersExtracted ?? 0;

    if (matched === 0) {
      return 'Aucun email ne correspondait à la recherche Gmail pour cette synchronisation.';
    }
    if (extracted === 0) {
      return `${formatCount(matched, 'email trouvé', 'emails trouvés')}, mais aucune offre exploitable n’a été extraite.`;
    }
  }

  return 'La source n’a renvoyé aucune offre pour cette synchronisation. Ce résultat est distinct d’une erreur de connecteur.';
}

export function ConnectorSyncResultRow({ name, state, result, diagnostics, error }: Props) {
  const emptyExplanation = zeroResultExplanation(state, result, diagnostics, error);
  const hasDetails = Boolean(
    error
    || diagnostics
    || emptyExplanation
    || result?.durationMs != null
    || (result && ((result.received ?? 0) > 0 || (result.merged ?? 0) > 0 || (result.profileFiltered ?? 0) > 0 || (result.failed ?? 0) > 0)),
  );
  const status = stateLabel(state);
  const summary = compactSummary(result);
  const statusId = `connector-sync-${name.toLowerCase().replace(/[^a-z0-9]+/g, '-')}-status`;

  return (
    <section
      className="notice"
      data-testid="connector-sync-result-row"
      aria-label={`Synchronisation ${name}`}
      aria-describedby={statusId}
    >
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}>
        <div className="actions" style={{ alignItems: 'center' }}>
          <strong>{name}</strong>
          <span id={statusId} role="status" aria-live="polite" aria-atomic="true">
            <Badge tone={stateTone(state)}>{status}</Badge>
          </span>
        </div>
        <div className="small muted">{summary}</div>
      </div>

      {state === 'running' && (
        <div className="small muted" style={{ marginTop: 6 }}>
          Récupération, normalisation et import en cours…
        </div>
      )}

      {hasDetails && (
        <details style={{ marginTop: 8 }}>
          <summary className="small" style={{ cursor: 'pointer' }}>
            Voir le détail de {name}
          </summary>
          <div className="small muted" style={{ marginTop: 8 }}>
            {result && (
              <div className="actions small">
                <span>{formatCount(result.received ?? 0, 'offre récupérée', 'offres récupérées')}</span>
                <span>{formatCount(result.imported ?? 0, 'nouvelle offre', 'nouvelles offres')}</span>
                <span>{formatCount(result.merged ?? 0, 'source fusionnée', 'sources fusionnées')}</span>
                <span>{formatCount(result.duplicates ?? 0, 'offre déjà connue', 'offres déjà connues')}</span>
                {(result.profileFiltered ?? 0) > 0 && <span>{formatCount(result.profileFiltered ?? 0, 'offre hors profil', 'offres hors profil')}</span>}
                {(result.failed ?? 0) > 0 && <span>{formatCount(result.failed ?? 0, 'échec', 'échecs')}</span>}
                {result.durationMs != null && <span>Durée : {formatDuration(result.durationMs)}</span>}
              </div>
            )}

            {emptyExplanation && (
              <div style={{ marginTop: 8 }}>
                <strong>Résultat vide :</strong> {emptyExplanation}
              </div>
            )}

            {(result?.profileFiltered ?? 0) > 0 && (
              <div style={{ marginTop: 8 }}>
                <strong>Hors profil :</strong>{' '}
                le filtre d’admission a confirmé une incompatibilité de profil (score sous le seuil, prérequis manquant ou conflit explicite). Les offres écartées ne sont pas enregistrées.
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
    </section>
  );
}
