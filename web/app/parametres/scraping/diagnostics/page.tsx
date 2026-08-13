'use client';

import { useEffect, useMemo, useState } from 'react';

import { Badge, Card, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

import styles from './diagnostics.module.css';

type ScraperMode = 'AUTO' | 'HTTP' | 'BROWSER';

type CustomScraperSource = {
  id: number;
  name: string;
  domain: string;
  mode: ScraperMode;
  enabled: boolean;
  authorizationConfirmed: boolean;
};

type SearchHistory = {
  page: number;
  url: string;
  statusCode: number;
  nextUrl: string | null;
  strategy: string | null;
  confidence: number | string | null;
};

type SearchDiagnostic = {
  keyword: string | null;
  requestedUrl: string;
  pageLimit: number;
  pagesFetched: number;
  rawCandidateCount: number;
  recommendedMode: 'HTTP' | 'BROWSER';
  statusCodes: number[];
  lastStatusCode: number | null;
  durationMs: number;
  stopReason: string;
  error: string | null;
  history: SearchHistory[];
};

type SearchCandidate = {
  sourceUrl: string;
  title: string;
  company: string;
  location: string;
  contractType: string;
  workMode: string;
  rawData: Record<string, unknown>;
};

type MultiSearchPreview = {
  searchCount: number;
  executedSearchCount: number;
  requestedMaxListingRequests: number;
  globalPageBudget: number;
  budgetLimited: boolean;
  networkRequests: number;
  durationMs: number;
  rawCandidateCount: number;
  duplicateCount: number;
  candidateCount: number;
  requiresBrowser: boolean;
  stoppedEarly: boolean;
  globalError: string | null;
  diagnostics: SearchDiagnostic[];
  candidates: SearchCandidate[];
};

const stopReasonLabels: Record<string, string> = {
  NO_PAGE_BUDGET: 'Budget épuisé avant la recherche',
  PAGE_LIMIT_REACHED: 'Limite de pages atteinte',
  NO_NEXT_PAGE: 'Aucune page suivante détectée',
  UNSAFE_NEXT_PAGE: 'Pagination hors domaine ou non HTTPS',
  LOOP_DETECTED: 'Boucle de pagination détectée',
  PAGE_FETCH_ERROR: 'Erreur d’accès à la source',
  BROWSER_REQUIRED: 'Rendu navigateur recommandé',
};

function stopReasonLabel(value: string): string {
  return stopReasonLabels[value] ?? value;
}

function sourceStatusTone(source: CustomScraperSource): 'good' | 'warn' | 'neutral' {
  if (!source.authorizationConfirmed) return 'warn';
  if (!source.enabled) return 'neutral';
  return 'good';
}

function sourceStatusLabel(source: CustomScraperSource): string {
  if (!source.authorizationConfirmed) return 'Autorisation à confirmer';
  if (!source.enabled) return 'Désactivée';
  return 'Prête à tester';
}

function candidateKeywords(candidate: SearchCandidate): string[] {
  const value = candidate.rawData.discoveredByKeywords;
  return Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : [];
}

export default function CustomScraperSearchDiagnosticsPage() {
  const [sources, setSources] = useState<CustomScraperSource[] | null>(null);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [result, setResult] = useState<MultiSearchPreview | null>(null);
  const [loadingPreview, setLoadingPreview] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;

    void api<CustomScraperSource[]>('/custom-scrapers')
      .then((items) => {
        if (!active) return;
        setSources(items);
        setSelectedId((current) => current ?? items[0]?.id ?? null);
      })
      .catch((caughtError: unknown) => {
        if (active) setError(getErrorMessage(caughtError));
      });

    return () => {
      active = false;
    };
  }, []);

  const selectedSource = useMemo(
    () => sources?.find((source) => source.id === selectedId) ?? null,
    [selectedId, sources],
  );

  const runPreview = async (): Promise<void> => {
    if (selectedSource === null) return;

    setLoadingPreview(true);
    setError('');
    setResult(null);

    try {
      const preview = await api<MultiSearchPreview>(`/custom-scrapers/${selectedSource.id}/search-preview`, {
        method: 'POST',
      });
      setResult(preview);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setLoadingPreview(false);
    }
  };

  if (sources === null) {
    return error !== '' ? <ErrorBox message={error} /> : <Loading />;
  }

  return (
    <>
      <PageHeader
        title="Diagnostics multi-recherche"
        description="Exécute manuellement le plan multi-mots-clés d’une source autorisée, inspecte le budget réseau, la pagination et les doublons avant toute synchronisation réelle."
      />

      {error !== '' && <ErrorBox message={error} />}

      <Card>
        <div className={styles.toolbar}>
          <label className={styles.sourcePicker}>
            Source à tester
            <select
              value={selectedId ?? ''}
              onChange={(event) => {
                setSelectedId(Number(event.target.value));
                setResult(null);
                setError('');
              }}
            >
              {sources.map((source) => (
                <option key={source.id} value={source.id}>{source.name} · {source.domain}</option>
              ))}
            </select>
          </label>

          {selectedSource !== null && (
            <div className={styles.sourceState}>
              <Badge tone={sourceStatusTone(selectedSource)}>{sourceStatusLabel(selectedSource)}</Badge>
              <span>Mode : {selectedSource.mode}</span>
            </div>
          )}

          <button
            className="btn"
            type="button"
            disabled={selectedSource === null || loadingPreview || selectedSource.authorizationConfirmed !== true || selectedSource.mode === 'BROWSER'}
            onClick={() => void runPreview()}
          >
            {loadingPreview ? 'Analyse en cours…' : 'Exécuter le preview multi-recherche'}
          </button>
        </div>

        {sources.length === 0 && <p className="muted">Aucune source personnalisée n’est configurée.</p>}
        {selectedSource !== null && !selectedSource.authorizationConfirmed && (
          <p className={styles.warningText}>Confirme d’abord l’autorisation de collecte sur la page principale Scraping.</p>
        )}
        {selectedSource?.mode === 'BROWSER' && (
          <p className={styles.warningText}>Cette source force Browser / Playwright. Le preview HTTP multi-recherche est volontairement désactivé.</p>
        )}
      </Card>

      {result !== null && (
        <>
          <section className={styles.metrics} aria-label="Résumé du preview multi-recherche">
            <Card><span>Recherches exécutées</span><strong>{result.executedSearchCount}/{result.searchCount}</strong></Card>
            <Card><span>Budget pages</span><strong>{result.globalPageBudget}</strong><small>{result.budgetLimited ? 'budget global limité' : 'budget complet'}</small></Card>
            <Card><span>Requêtes réseau</span><strong>{result.networkRequests}</strong><small>{result.durationMs} ms</small></Card>
            <Card><span>Offres uniques</span><strong>{result.candidateCount}</strong><small>{result.duplicateCount} doublon(s) fusionné(s)</small></Card>
          </section>

          {(result.requiresBrowser || result.stoppedEarly || result.globalError !== null) && (
            <Card>
              <div className={styles.alertRow}>
                <Badge tone="warn">Attention</Badge>
                <div>
                  <strong>{result.requiresBrowser ? 'Le rendu navigateur est recommandé.' : 'La collecte s’est arrêtée avant la fin du plan.'}</strong>
                  <p>{result.globalError ?? 'Consulte les diagnostics par mot-clé avant d’ajuster le mode ou le budget.'}</p>
                </div>
              </div>
            </Card>
          )}

          <Card>
            <h2 className="section-title">Diagnostic par mot-clé</h2>
            {result.diagnostics.length === 0 ? (
              <p className="muted">Aucune recherche n’a été exécutée.</p>
            ) : (
              <div className={styles.diagnosticList}>
                {result.diagnostics.map((diagnostic, index) => (
                  <article className={styles.diagnostic} key={`${diagnostic.keyword ?? 'fallback'}-${index}`}>
                    <div className={styles.diagnosticHeader}>
                      <div>
                        <strong>{diagnostic.keyword ?? 'URL de liste par défaut'}</strong>
                        <span>{diagnostic.requestedUrl}</span>
                      </div>
                      <Badge tone={diagnostic.error !== null || diagnostic.recommendedMode === 'BROWSER' ? 'warn' : 'good'}>
                        {diagnostic.recommendedMode}
                      </Badge>
                    </div>

                    <div className={styles.diagnosticStats}>
                      <span>Pages <strong>{diagnostic.pagesFetched}/{diagnostic.pageLimit}</strong></span>
                      <span>Candidats bruts <strong>{diagnostic.rawCandidateCount}</strong></span>
                      <span>HTTP <strong>{diagnostic.lastStatusCode ?? '—'}</strong></span>
                      <span>Durée <strong>{diagnostic.durationMs} ms</strong></span>
                    </div>

                    <p className={styles.stopReason}>{stopReasonLabel(diagnostic.stopReason)}</p>
                    {diagnostic.error !== null && <p className={styles.errorText}>{diagnostic.error}</p>}

                    {diagnostic.history.length > 0 && (
                      <details className={styles.history}>
                        <summary>Voir la pagination ({diagnostic.history.length} étape(s))</summary>
                        <div>
                          {diagnostic.history.map((entry) => (
                            <p key={`${entry.page}-${entry.url}`}>
                              <strong>Page {entry.page}</strong> · HTTP {entry.statusCode} · {entry.url}
                              {entry.nextUrl !== null ? <> → {entry.nextUrl}</> : null}
                            </p>
                          ))}
                        </div>
                      </details>
                    )}
                  </article>
                ))}
              </div>
            )}
          </Card>

          <Card>
            <h2 className="section-title">Offres détectées après déduplication</h2>
            {result.candidates.length === 0 ? (
              <p className="muted">Aucune offre exploitable détectée pendant ce preview.</p>
            ) : (
              <div className={styles.candidateList}>
                {result.candidates.slice(0, 20).map((candidate) => {
                  const keywords = candidateKeywords(candidate);
                  return (
                    <article key={`${candidate.sourceUrl}-${candidate.title}`}>
                      <div>
                        <strong>{candidate.title || 'Titre non détecté'}</strong>
                        <span>{candidate.company || 'Entreprise inconnue'} · {candidate.location || 'Lieu inconnu'}</span>
                      </div>
                      <div className={styles.candidateMeta}>
                        {candidate.contractType && <span>{candidate.contractType}</span>}
                        {candidate.workMode && <span>{candidate.workMode}</span>}
                        {keywords.length > 0 && <span>via {keywords.join(', ')}</span>}
                      </div>
                    </article>
                  );
                })}
              </div>
            )}
            {result.candidates.length > 20 && <p className="muted">20 offres affichées sur {result.candidates.length}.</p>}
          </Card>
        </>
      )}
    </>
  );
}
