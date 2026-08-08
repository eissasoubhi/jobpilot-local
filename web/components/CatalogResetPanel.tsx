'use client';

import { useState } from 'react';

import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

import styles from './CatalogResetPanel.module.css';

type CatalogResetResult = {
  message: string;
  reset: {
    busy: boolean;
    deletedOffers: number;
    deletedApplications: number;
    deletedOccurrences: number;
  };
  sync?: {
    received?: number;
    imported?: number;
    merged?: number;
    profileFiltered?: number;
    failed?: number;
    busy?: boolean;
    skipped?: boolean;
  };
};

const CONFIRMATION_LABEL = 'REINITIALISER';
const API_CONFIRMATION = 'RESET_OFFERS';

export function CatalogResetPanel() {
  const [confirmation, setConfirmation] = useState('');
  const [resetting, setResetting] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState<CatalogResetResult | null>(null);

  const canReset = confirmation.trim() === CONFIRMATION_LABEL && !resetting;

  const resetCatalog = async (): Promise<void> => {
    if (!canReset) return;

    const confirmed = window.confirm(
      'Cette action supprime toutes les offres et les candidatures qui leur sont liées, y compris l’historique des statuts. Les CV, le profil, les paramètres et les connecteurs sont conservés. Continuer ?',
    );
    if (!confirmed) return;

    setResetting(true);
    setError('');
    setResult(null);

    try {
      const response = await api<CatalogResetResult>('/job-search/reset', {
        method: 'POST',
        body: JSON.stringify({ confirmation: API_CONFIRMATION }),
      });
      setResult(response);
      setConfirmation('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setResetting(false);
    }
  };

  return (
    <section className={styles.panel} aria-labelledby="catalog-reset-title">
      <div className={styles.heading}>
        <div>
          <div className={styles.eyebrow}>Zone dangereuse</div>
          <h2 id="catalog-reset-title">Réinitialiser les offres</h2>
        </div>
        <span className={styles.badge}>Destructif</span>
      </div>

      <p className={styles.description}>
        Supprime le catalogue actuel puis relance immédiatement une recherche forcée sur toutes les sources activées,
        configurées et autorisées. Le nouveau filtre de matching IA s’appliquera aux offres récupérées.
      </p>

      <div className={styles.keepGrid}>
        <div>
          <strong>Supprimé</strong>
          <span>Offres, occurrences de sources et candidatures liées.</span>
        </div>
        <div>
          <strong>Conservé</strong>
          <span>Profil, CV, paramètres, clés API, connecteurs, cache IA et historique de synchronisation.</span>
        </div>
      </div>

      <div className={styles.warning}>
        Les candidatures déjà marquées comme envoyées, refusées ou en entretien seront aussi supprimées si elles sont liées à une offre du catalogue.
      </div>

      <label className={styles.confirmation}>
        Pour déverrouiller le bouton, écris <code>{CONFIRMATION_LABEL}</code>
        <input
          value={confirmation}
          autoComplete="off"
          aria-label="Confirmation de réinitialisation des offres"
          placeholder={CONFIRMATION_LABEL}
          disabled={resetting}
          onChange={(event) => {
            setConfirmation(event.target.value);
            setError('');
            setResult(null);
          }}
        />
      </label>

      <div className={styles.actions}>
        <button
          className={styles.resetButton}
          type="button"
          disabled={!canReset}
          onClick={() => void resetCatalog()}
        >
          {resetting ? 'Suppression et resynchronisation…' : 'Supprimer et resynchroniser'}
        </button>
        <span>Les critères de recherche actuellement enregistrés seront utilisés.</span>
      </div>

      {error !== '' && <div className={styles.error} role="alert">{error}</div>}

      {result && (
        <div className={styles.success} role="status">
          <strong>{result.message}</strong>
          <div className={styles.metrics}>
            <span>{result.reset.deletedOffers} offres supprimées</span>
            <span>{result.reset.deletedApplications} candidatures supprimées</span>
            <span>{result.sync?.imported ?? 0} nouvelles offres</span>
            <span>{result.sync?.profileFiltered ?? 0} hors profil filtrées</span>
            <span>{result.sync?.failed ?? 0} erreurs</span>
          </div>
          <a href="/offres">Voir le nouveau catalogue →</a>
        </div>
      )}
    </section>
  );
}
