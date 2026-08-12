'use client';

import { useEffect, useMemo, useState } from 'react';

import { Badge } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

export type SearchableCustomScraperSource = {
  id: number;
  name: string;
  searchUrlTemplate: string | null;
  searchKeywords: string[];
  maxPages: number;
  authorizationConfirmed: boolean;
};

type SearchPlanItem = {
  keyword: string | null;
  url: string;
  pageLimit: number;
};

type SearchPlan = {
  configured: boolean;
  searchCount: number;
  maxPagesPerSearch: number;
  requestedMaxListingRequests: number;
  globalPageBudget: number;
  budgetLimited: boolean;
  searches: SearchPlanItem[];
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
};

type SearchPreview = {
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
};

type Props = {
  source: SearchableCustomScraperSource;
  onUpdated: (source: SearchableCustomScraperSource) => void;
};

function sameKeywords(left: string[], right: string[]): boolean {
  return left.length === right.length && left.every((value, index) => value === right[index]);
}

function stopReasonLabel(reason: string): string {
  return {
    NO_NEXT_PAGE: 'Fin des résultats',
    PAGE_LIMIT_REACHED: 'Limite de pages atteinte',
    BROWSER_REQUIRED: 'JavaScript / Browser requis',
    PAGE_FETCH_ERROR: 'Collecte interrompue',
    LOOP_DETECTED: 'Boucle de pagination détectée',
    UNSAFE_NEXT_PAGE: 'Pagination externe refusée',
    NO_PAGE_BUDGET: 'Aucun budget de page',
  }[reason] ?? reason;
}

function durationLabel(milliseconds: number): string {
  if (milliseconds < 1_000) return `${milliseconds} ms`;
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(milliseconds / 1_000)} s`;
}

function statusTone(status: number | null): 'good' | 'warn' | 'blue' {
  if (status === null) return 'blue';
  if (status >= 200 && status < 300) return 'good';
  return 'warn';
}

export function CustomScraperSearchDiagnosticsPanel({ source, onUpdated }: Props) {
  const [urlTemplate, setUrlTemplate] = useState(source.searchUrlTemplate ?? '');
  const [keywords, setKeywords] = useState(source.searchKeywords);
  const [keywordInput, setKeywordInput] = useState('');
  const [plan, setPlan] = useState<SearchPlan | null>(null);
  const [preview, setPreview] = useState<SearchPreview | null>(null);
  const [saving, setSaving] = useState(false);
  const [planning, setPlanning] = useState(false);
  const [testing, setTesting] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  useEffect(() => {
    setUrlTemplate(source.searchUrlTemplate ?? '');
    setKeywords(source.searchKeywords);
    setPlan(null);
    setPreview(null);
    setError('');
    setNotice('');
  }, [source.id, source.searchKeywords, source.searchUrlTemplate]);

  const dirty = useMemo(
    () => urlTemplate.trim() !== (source.searchUrlTemplate ?? '').trim() || !sameKeywords(keywords, source.searchKeywords),
    [keywords, source.searchKeywords, source.searchUrlTemplate, urlTemplate],
  );

  const addKeyword = (): void => {
    const value = keywordInput.trim();
    if (value === '') return;
    if (value.length > 80) {
      setError('Un mot-clé ne peut pas dépasser 80 caractères.');
      return;
    }
    if (keywords.length >= 20) {
      setError('Cette source est limitée à 20 mots-clés.');
      return;
    }
    if (keywords.some((keyword) => keyword.localeCompare(value, undefined, { sensitivity: 'accent' }) === 0)) {
      setKeywordInput('');
      return;
    }

    setKeywords((current) => [...current, value]);
    setKeywordInput('');
    setError('');
    setPlan(null);
    setPreview(null);
  };

  const removeKeyword = (keyword: string): void => {
    setKeywords((current) => current.filter((value) => value !== keyword));
    setPlan(null);
    setPreview(null);
  };

  const saveConfiguration = async (): Promise<SearchableCustomScraperSource> => {
    const template = urlTemplate.trim();
    if (keywords.length > 0 && template === '') {
      throw new Error('Ajoute une URL de recherche contenant {keyword} avant d’enregistrer ces mots-clés.');
    }

    const updated = await api<SearchableCustomScraperSource>(`/custom-scrapers/${source.id}`, {
      method: 'PATCH',
      body: JSON.stringify({
        searchUrlTemplate: template === '' ? null : template,
        searchKeywords: keywords,
      }),
    });
    onUpdated(updated);
    setNotice('Configuration de recherche enregistrée.');
    setPlan(null);
    setPreview(null);

    return updated;
  };

  const save = async (): Promise<void> => {
    setSaving(true);
    setError('');
    setNotice('');
    try {
      await saveConfiguration();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const loadPlan = async (): Promise<void> => {
    if (dirty) {
      setError('Enregistre les mots-clés et le template avant de calculer le plan.');
      return;
    }

    setPlanning(true);
    setError('');
    setNotice('');
    try {
      const result = await api<SearchPlan>(`/custom-scrapers/${source.id}/search-plan`);
      setPlan(result);
      setNotice(`${result.searchCount} recherche(s) préparée(s), sans appel au site source.`);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setPlanning(false);
    }
  };

  const runPreview = async (): Promise<void> => {
    if (dirty) {
      setError('Enregistre les mots-clés et le template avant de tester le connecteur.');
      return;
    }
    if (!source.authorizationConfirmed) {
      setError('L’autorisation de collecte doit être confirmée avant le test réseau.');
      return;
    }

    setTesting(true);
    setError('');
    setNotice('');
    try {
      const result = await api<SearchPreview>(`/custom-scrapers/${source.id}/search-preview`, { method: 'POST' });
      setPreview(result);
      setNotice(`${result.candidateCount} offre(s) unique(s) détectée(s) après déduplication.`);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setTesting(false);
    }
  };

  return (
    <section className="notice" aria-label={`Recherches ciblées ${source.name}`}>
      <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <strong>Recherches ciblées</strong>
          <div className="muted" style={{ marginTop: 4 }}>
            Recherche uniquement les technologies utiles au lieu de parcourir tout le catalogue.
          </div>
        </div>
        <div className="actions">
          <Badge tone={keywords.length > 0 ? 'blue' : 'warn'}>{keywords.length} mot(s)-clé(s)</Badge>
          {plan && <Badge tone={plan.budgetLimited ? 'warn' : 'good'}>{plan.globalPageBudget} pages max</Badge>}
        </div>
      </div>

      <div className="actions" style={{ marginTop: 12, alignItems: 'center' }}>
        {keywords.map((keyword) => (
          <button
            key={keyword}
            type="button"
            className="btn secondary small"
            aria-label={`Retirer le mot-clé ${keyword}`}
            onClick={() => removeKeyword(keyword)}
          >
            {keyword} ×
          </button>
        ))}
        {keywords.length === 0 && <span className="muted">Aucun mot-clé : l’URL de liste générale sera utilisée.</span>}
      </div>

      <div className="actions" style={{ marginTop: 10, alignItems: 'end' }}>
        <label style={{ flex: 1 }}>
          Ajouter un mot-clé
          <input
            value={keywordInput}
            placeholder="PHP, Symfony, Vue.js, React.js…"
            onChange={(event) => setKeywordInput(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                event.preventDefault();
                addKeyword();
              }
            }}
          />
        </label>
        <button className="btn secondary" type="button" onClick={addKeyword}>Ajouter</button>
      </div>

      <details style={{ marginTop: 12 }}>
        <summary><strong>Configuration avancée</strong></summary>
        <div style={{ marginTop: 10 }}>
          <label>
            Template URL de recherche
            <input
              value={urlTemplate}
              placeholder="https://site.example/jobs?q={keyword}"
              onChange={(event) => {
                setUrlTemplate(event.target.value);
                setPlan(null);
                setPreview(null);
              }}
            />
          </label>
          <div className="muted" style={{ marginTop: 6 }}>
            Utilise <code>{'{keyword}'}</code>. Le backend vérifie HTTPS, le domaine et les placeholders autorisés.
          </div>
        </div>
      </details>

      {error !== '' && <div className="notice warning" role="alert" style={{ marginTop: 12 }}>{error}</div>}
      {notice !== '' && <div className="muted" role="status" style={{ marginTop: 10 }}>{notice}</div>}

      <div className="actions" style={{ marginTop: 12 }}>
        <button className="btn secondary" type="button" disabled={!dirty || saving || planning || testing} onClick={() => void save()}>
          {saving ? 'Enregistrement…' : 'Enregistrer'}
        </button>
        <button className="btn secondary" type="button" disabled={dirty || saving || planning || testing} onClick={() => void loadPlan()}>
          {planning ? 'Calcul du plan…' : 'Voir le plan'}
        </button>
        <button className="btn" type="button" disabled={dirty || saving || planning || testing} onClick={() => void runPreview()}>
          {testing ? 'Test en cours…' : 'Tester les recherches'}
        </button>
      </div>

      {plan && (
        <div style={{ marginTop: 14 }}>
          <div className="grid three" aria-label="Budget des recherches">
            <div><div className="small muted">Recherches</div><strong>{plan.searchCount}</strong></div>
            <div><div className="small muted">Pages autorisées</div><strong>{plan.globalPageBudget}</strong></div>
            <div><div className="small muted">Pages théoriques</div><strong>{plan.requestedMaxListingRequests}</strong></div>
          </div>
          {plan.budgetLimited && (
            <div className="notice warning" style={{ marginTop: 10 }}>
              Le budget global borne le plan : JobPilot répartit les pages entre les mots-clés au lieu de multiplier les requêtes.
            </div>
          )}
          <div className="stack" style={{ marginTop: 10 }}>
            {plan.searches.map((search, index) => (
              <div className="notice" key={`${search.keyword ?? 'catalogue'}-${index}`}>
                <div className="actions" style={{ justifyContent: 'space-between' }}>
                  <strong>{search.keyword ?? 'Catalogue général'}</strong>
                  <Badge tone="blue">{search.pageLimit} page(s)</Badge>
                </div>
                <code style={{ display: 'block', marginTop: 6, overflowWrap: 'anywhere' }}>{search.url}</code>
              </div>
            ))}
          </div>
        </div>
      )}

      {preview && (
        <div style={{ marginTop: 14 }}>
          <div className="grid three" aria-label="Résultats du test de recherches">
            <div><div className="small muted">Offres brutes</div><strong>{preview.rawCandidateCount}</strong></div>
            <div><div className="small muted">Doublons</div><strong>{preview.duplicateCount}</strong></div>
            <div><div className="small muted">Offres uniques</div><strong>{preview.candidateCount}</strong></div>
            <div><div className="small muted">Requêtes réseau</div><strong>{preview.networkRequests}</strong></div>
            <div><div className="small muted">Durée</div><strong>{durationLabel(preview.durationMs)}</strong></div>
            <div><div className="small muted">Recherches exécutées</div><strong>{preview.executedSearchCount}/{preview.searchCount}</strong></div>
          </div>

          {preview.globalError && (
            <div className="notice warning" role="alert" style={{ marginTop: 10 }}>
              <strong>Collecte interrompue.</strong> {preview.globalError}
            </div>
          )}
          {preview.requiresBrowser && (
            <div className="notice warning" style={{ marginTop: 10 }}>
              <strong>Browser requis.</strong> Une page publique semble nécessiter JavaScript. Le test HTTP s’arrête sans tenter de contournement.
            </div>
          )}

          <div className="stack" style={{ marginTop: 10 }}>
            {preview.diagnostics.map((diagnostic, index) => (
              <div className="notice" key={`${diagnostic.keyword ?? 'catalogue'}-${index}`}>
                <div className="actions" style={{ justifyContent: 'space-between' }}>
                  <div className="actions">
                    <strong>{diagnostic.keyword ?? 'Catalogue général'}</strong>
                    <Badge tone={statusTone(diagnostic.lastStatusCode)}>
                      {diagnostic.lastStatusCode === null ? 'Pas de réponse HTTP' : `HTTP ${diagnostic.lastStatusCode}`}
                    </Badge>
                  </div>
                  <div className="actions">
                    <Badge tone="blue">{diagnostic.pagesFetched}/{diagnostic.pageLimit} page(s)</Badge>
                    <Badge tone="blue">{durationLabel(diagnostic.durationMs)}</Badge>
                  </div>
                </div>
                <code style={{ display: 'block', marginTop: 6, overflowWrap: 'anywhere' }}>{diagnostic.requestedUrl}</code>
                <div className="muted" style={{ marginTop: 6 }}>
                  {diagnostic.rawCandidateCount} offre(s) brute(s) · {stopReasonLabel(diagnostic.stopReason)} · mode {diagnostic.recommendedMode}
                </div>
                {diagnostic.error && <div className="notice warning" style={{ marginTop: 8 }}>{diagnostic.error}</div>}
              </div>
            ))}
          </div>
        </div>
      )}
    </section>
  );
}
