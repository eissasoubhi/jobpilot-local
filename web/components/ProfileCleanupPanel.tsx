'use client';

import { useState } from 'react';

import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

import styles from './CatalogResetPanel.module.css';

type ProfileCleanupResult = {
  message: string;
  cleanup: {
    busy: boolean;
    scanned: number;
    deletedOffers: number;
    deletedApplications: number;
    deletedOccurrences: number;
    manuallyRejected: number;
    reusedStoredAi: number;
    aiChecks: number;
    protectedHistory: number;
    kept: number;
  };
};

const API_CONFIRMATION = 'CLEAN_PROFILE_MISMATCHES';

export function ProfileCleanupPanel() {
  const [cleaning, setCleaning] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState<ProfileCleanupResult | null>(null);

  const cleanup = async (): Promise<void> => {
    const confirmed = window.confirm(
      'Supprimer uniquement les offres clairement hors profil encore à traiter ? Les candidatures déjà envoyées, en entretien, refusées ou avec un historique traité seront conservées.',
    );
    if (!confirmed) return;

    setCleaning(true);
    setError('');
    setResult(null);

    try {
      const response = await api<ProfileCleanupResult>('/job-search/cleanup-profile-mismatches', {
        method: 'POST',
        body: JSON.stringify({ confirmation: API_CONFIRMATION }),
      });
      setResult(response);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setCleaning(false);
    }
  };

  return (
    <section className={styles.panel} aria-labelledby="profile-cleanup-title">
      <div className={styles.heading}>
        <div>
          <div className={styles.eyebrow}>Nettoyage ciblé</div>
          <h2 id="profile-cleanup-title">Supprimer les offres hors profil</h2>
        </div>
        <span className={styles.badge}>Sélectif</span>
      </div>

      <p className={styles.description}>
        Analyse le catalogue actuel et supprime uniquement les offres que JobPilot peut classer comme hors profil avec un niveau de confiance suffisant. Les cas ambigus restent en base.
      </p>

      <div className={styles.keepGrid}>
        <div>
          <strong>Supprimé</strong>
          <span>Offres marquées « Ne correspond pas » ou NO_MATCH à haute confiance, tant qu’elles ne portent pas un historique de candidature traité.</span>
        </div>
        <div>
          <strong>Conservé</strong>
          <span>MATCH, REVIEW, faible confiance, doute/quota indisponible, et toute candidature déjà envoyée ou suivie.</span>
        </div>
      </div>

      <div className={styles.warning}>
        Cette action ne relance pas la synchronisation. Les prochaines synchronisations continueront à utiliser le filtre IA avant enregistrement.
      </div>

      <div className={styles.actions}>
        <button
          className={styles.resetButton}
          type="button"
          disabled={cleaning}
          onClick={() => void cleanup()}
        >
          {cleaning ? 'Analyse et nettoyage…' : 'Nettoyer les offres hors profil'}
        </button>
        <span>Les analyses déjà enregistrées sont réutilisées en priorité ; le cache/quota IA reste protégé.</span>
      </div>

      {error !== '' && <div className={styles.error} role="alert">{error}</div>}

      {result && (
        <div className={styles.success} role="status">
          <strong>{result.message}</strong>
          <div className={styles.metrics}>
            <span>{result.cleanup.scanned} offres analysées</span>
            <span>{result.cleanup.deletedOffers} offres supprimées</span>
            <span>{result.cleanup.manuallyRejected} rejetées manuellement</span>
            <span>{result.cleanup.reusedStoredAi} analyses IA réutilisées</span>
            <span>{result.cleanup.protectedHistory} historiques protégés</span>
            <span>{result.cleanup.kept} conservées</span>
          </div>
          <a href="/offres">Voir le catalogue nettoyé →</a>
        </div>
      )}
    </section>
  );
}
