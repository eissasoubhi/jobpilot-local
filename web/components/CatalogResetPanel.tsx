'use client';

import { useState } from 'react';

import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

import styles from './CatalogResetPanel.module.css';

type CatalogCleanupResult = {
  message: string;
  cleanup: {
    busy: boolean;
    scannedOffers: number;
    deletedOffers: number;
    deletedApplications: number;
    deletedOccurrences: number;
    deletedMarkedNotMatch: number;
    deletedStoredAiNoMatch: number;
    deletedAiNoMatch: number;
    protectedHistoryOffers: number;
    keptOffers: number;
  };
};

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
const CLEANUP_API_CONFIRMATION = 'CLEAN_NO_MATCH';

export function CatalogResetPanel() {
  const [confirmation, setConfirmation] = useState('');
  const [resetting, setResetting] = useState(false);
  const [cleaning, setCleaning] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState<CatalogResetResult | null>(null);
  const [cleanupResult, setCleanupResult] = useState<CatalogCleanupResult | null>(null);

  const canReset = confirmation.trim() === CONFIRMATION_LABEL && !resetting && !cleaning;

  const cleanupProfile = async (): Promise<void> => {
    if (cleaning || resetting) return;

    const confirmed = window.confirm(
      'Supprimer uniquement les offres clairement hors profil ? Les candidatures non envoyées liées à ces offres seront supprimées. Les candidatures déjà traitées (envoyées, entretiens, réponses, refus, etc.) sont protégées.',
    );
    if (!confirmed) return;

    setCleaning(true);
    setError('');
    setCleanupResult(null);

    try {
      const response = await api<CatalogCleanupResult>('/job-search/cleanup-profile', {
        method: 'POST',
        body: JSON.stringify({ confirmation: CLEANUP_API_CONFIRMATION }),
      });
      setCleanupResult(response);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setCleaning(false);
    }
  };

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
      <div className={styles.cleanupBox}>
        <div className={styles.heading}>
          <div>
            <div className={styles.cleanupEyebrow}>Nettoyage sélectif</div>
            <h2>Nettoyer les offres hors profil</h2>
          </div>
          <span className={styles.cleanupBadge}>Recommandé</span>
        </div>

        <p className={styles.description}>
          Analyse le catalogue déjà présent et supprime seulement les offres clairement classées hors profil. Les offres
          ambiguës, les matchs possibles et les candidatures déjà traitées sont conservés.
        </p>

        <div className={styles.keepGrid}>
          <div>
            <strong>Peut être supprimé</strong>
            <span>Offres marquées « ne correspond pas » ou NO_MATCH IA à forte confiance, avec preuve concrète.</span>
          </div>
          <div>
            <strong>Protégé</strong>
            <span>Matchs, REVIEW, faible confiance et toute offre avec une candidature déjà réellement traitée.</span>
          </div>
        </div>

        <div className={styles.actions}>
          <button
            className={styles.cleanupButton}
            type="button"
            disabled={cleaning || resetting}
            onClick={() => void cleanupProfile()}
          >
            {cleaning ? 'Nettoyage en cours…' : 'Nettoyer les offres hors profil'}
          </button>
          <span>Le cache et l’analyse IA existante sont réutilisés avant tout nouvel appel fournisseur.</span>
        </div>

        {cleanupResult && (
          <div className={styles.success} role="status" data-testid="cleanup-result">
            <strong>{cleanupResult.message}</strong>
            <div className={styles.metrics}>
              <span>{cleanupResult.cleanup.scannedOffers} offres analysées</span>
              <span>{cleanupResult.cleanup.deletedOffers} hors profil supprimées</span>
              <span>{cleanupResult.cleanup.deletedApplications} candidatures locales supprimées</span>
              <span>{cleanupResult.cleanup.protectedHistoryOffers} historiques protégés</span>
              <span>{cleanupResult.cleanup.keptOffers} offres conservées</span>
            </div>
            <a href="/offres">Voir le catalogue nettoyé →</a>
          </div>
        )}
      </div>

      <div className={styles.divider} />

      <div className={styles.heading}>
        <div>
          <div className={styles.eyebrow}>Zone dangereuse</div>
          <h2 id="catalog-reset-title">Réinitialiser toutes les offres</h2>
        </div>
        <span className={styles.badge}>Destructif</span>
      </div>

      <p className={styles.description}>
        Supprime le catalogue actuel puis relance immédiatement une recherche forcée sur toutes les sources activées,
        configurées et autorisées. Utilise cette action seulement si tu veux réellement repartir de zéro.
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
          disabled={resetting || cleaning}
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
