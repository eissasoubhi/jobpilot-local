'use client';

import { useEffect, useState } from 'react';

import { Badge, Card, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

type ScraperMode = 'AUTO' | 'HTTP' | 'BROWSER';
type Confidence = 'HIGH' | 'MEDIUM' | 'LOW';

type CustomScraperSource = {
  id: number;
  name: string;
  domain: string;
  listingUrl: string;
  detailExampleUrl: string | null;
  mode: ScraperMode;
  enabled: boolean;
  authorizationConfirmed: boolean;
  authorizationCheckedAt: string | null;
  authorizationReference: string | null;
  syncIntervalMinutes: number;
  maxPages: number;
  maxDetails: number;
};

type HttpDiagnostic = {
  requestedUrl: string;
  finalUrl: string;
  statusCode: number;
  contentType: string | null;
  responseBytes: number;
  networkRequests: number;
  fromCache: boolean;
};

type ScraperDiagnostic = {
  configuredMode: ScraperMode;
  recommendedMode: Exclude<ScraperMode, 'AUTO'>;
  effectiveMode: Exclude<ScraperMode, 'AUTO'>;
  confidence: Confidence;
  reason: string;
  browserVerificationRequired: boolean;
  signals: {
    visibleTextCharacters: number;
    jobStructuredData: number;
    jobLikeLinks: number;
    jobKeywordHits: number;
    scriptTags: number;
    javascriptMarkers: number;
    emptyAppShell: boolean;
  };
  http: HttpDiagnostic;
};

type ExtractedOffer = {
  title: string;
  company: string | null;
  location: string | null;
  contractType: string | null;
  workMode: 'REMOTE' | 'HYBRID' | 'ONSITE' | 'UNKNOWN';
  salaryMin: number | null;
  salaryMax: number | null;
  tjmMin: number | null;
  tjmMax: number | null;
  publishedAt: string | null;
  description: string | null;
  sourceUrl: string | null;
  technologies: string[];
};

type ExtractionPreview = {
  configuredMode: ScraperMode;
  recommendedMode: Exclude<ScraperMode, 'AUTO'>;
  effectiveMode: Exclude<ScraperMode, 'AUTO'>;
  requiresBrowser: boolean;
  modeConfidence: Confidence;
  modeReason: string;
  aiCalled: boolean;
  ai: null | {
    model: string;
    cacheHit: boolean;
    confidence: number;
    notes: string[];
  };
  dom: null | {
    originalBytes: number;
    compactedCharacters: number;
    truncated: boolean;
    structuredDataBlocks: number;
  };
  offers: ExtractedOffer[];
  message: string;
  http: HttpDiagnostic;
};

type NewSourceForm = {
  name: string;
  listingUrl: string;
  detailExampleUrl: string;
  mode: ScraperMode;
  enabled: boolean;
  authorizationConfirmed: boolean;
  authorizationCheckedAt: string;
  authorizationReference: string;
  syncIntervalMinutes: number;
  maxPages: number;
  maxDetails: number;
};

const initialForm: NewSourceForm = {
  name: '',
  listingUrl: '',
  detailExampleUrl: '',
  mode: 'AUTO',
  enabled: true,
  authorizationConfirmed: false,
  authorizationCheckedAt: '',
  authorizationReference: '',
  syncIntervalMinutes: 360,
  maxPages: 5,
  maxDetails: 20,
};

function modeLabel(mode: ScraperMode): string {
  if (mode === 'HTTP') return 'HTTP forcé';
  if (mode === 'BROWSER') return 'Browser / Playwright forcé';
  return 'Auto (recommandé)';
}

function intervalLabel(minutes: number): string {
  if (minutes % 1440 === 0) return `Toutes les ${minutes / 1440} j`;
  if (minutes % 60 === 0) return `Toutes les ${minutes / 60} h`;
  return `Toutes les ${minutes} min`;
}

function confidenceLabel(confidence: Confidence): string {
  if (confidence === 'HIGH') return 'Confiance élevée';
  if (confidence === 'MEDIUM') return 'Confiance moyenne';
  return 'Confiance faible';
}

function extractionConfidence(value: number): string {
  return `${Math.round(value * 100)} %`;
}

function compensationLabel(offer: ExtractedOffer): string | null {
  if (offer.tjmMin !== null || offer.tjmMax !== null) {
    const minimum = offer.tjmMin ?? offer.tjmMax;
    const maximum = offer.tjmMax ?? offer.tjmMin;
    return minimum === maximum ? `${minimum} € / j` : `${minimum}–${maximum} € / j`;
  }
  if (offer.salaryMin !== null || offer.salaryMax !== null) {
    const minimum = offer.salaryMin ?? offer.salaryMax;
    const maximum = offer.salaryMax ?? offer.salaryMin;
    return minimum === maximum
      ? `${minimum?.toLocaleString('fr-FR')} €`
      : `${minimum?.toLocaleString('fr-FR')}–${maximum?.toLocaleString('fr-FR')} €`;
  }
  return null;
}

export default function CustomScrapingSettingsPage() {
  const [sources, setSources] = useState<CustomScraperSource[] | null>(null);
  const [form, setForm] = useState<NewSourceForm>(initialForm);
  const [diagnostics, setDiagnostics] = useState<Record<number, ScraperDiagnostic>>({});
  const [previews, setPreviews] = useState<Record<number, ExtractionPreview>>({});
  const [testingId, setTestingId] = useState<number | null>(null);
  const [extractingId, setExtractingId] = useState<number | null>(null);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [saving, setSaving] = useState(false);

  const load = async (): Promise<void> => {
    try {
      setSources(await api<CustomScraperSource[]>('/custom-scrapers'));
      setError('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  useEffect(() => {
    void load();
  }, []);

  const createSource = async (): Promise<void> => {
    if (!form.authorizationConfirmed) {
      setError('Confirme d’abord que tu as vérifié que ce site autorise la collecte automatisée.');
      return;
    }

    setSaving(true);
    setError('');
    setMessage('');

    try {
      const payload: Record<string, unknown> = {
        ...form,
        detailExampleUrl: form.detailExampleUrl.trim() || null,
        authorizationReference: form.authorizationReference.trim() || null,
      };
      if (form.authorizationCheckedAt.trim() === '') delete payload.authorizationCheckedAt;

      const created = await api<CustomScraperSource>('/custom-scrapers', {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      setSources((current) => [...(current ?? []), created].sort((a, b) => a.name.localeCompare(b.name)));
      setForm(initialForm);
      setMessage(`${created.name} a été ajouté au registre de scraping.`);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const clearSourceResults = (sourceId: number): void => {
    setDiagnostics((current) => {
      const next = { ...current };
      delete next[sourceId];
      return next;
    });
    setPreviews((current) => {
      const next = { ...current };
      delete next[sourceId];
      return next;
    });
  };

  const patchSource = async (source: CustomScraperSource, patch: Record<string, unknown>): Promise<void> => {
    setError('');
    setMessage('');
    try {
      const updated = await api<CustomScraperSource>(`/custom-scrapers/${source.id}`, {
        method: 'PATCH',
        body: JSON.stringify(patch),
      });
      setSources((current) => (current ?? []).map((item) => item.id === updated.id ? updated : item));
      clearSourceResults(source.id);
      setMessage(`${updated.name} a été mis à jour.`);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  const diagnoseSource = async (source: CustomScraperSource): Promise<void> => {
    setTestingId(source.id);
    setError('');
    setMessage('');
    try {
      const result = await api<ScraperDiagnostic>(`/custom-scrapers/${source.id}/diagnose`, { method: 'POST' });
      setDiagnostics((current) => ({ ...current, [source.id]: result }));
      setMessage(`Diagnostic terminé pour ${source.name} : ${result.recommendedMode} recommandé.`);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setTestingId(null);
    }
  };

  const extractPreview = async (source: CustomScraperSource): Promise<void> => {
    setExtractingId(source.id);
    setError('');
    setMessage('');
    try {
      const result = await api<ExtractionPreview>(`/custom-scrapers/${source.id}/extract-preview`, { method: 'POST' });
      setPreviews((current) => ({ ...current, [source.id]: result }));
      setMessage(result.message);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setExtractingId(null);
    }
  };

  const deleteSource = async (source: CustomScraperSource): Promise<void> => {
    if (!window.confirm(`Supprimer ${source.name} du registre de scraping ?`)) return;

    setError('');
    setMessage('');
    try {
      await api<void>(`/custom-scrapers/${source.id}`, { method: 'DELETE' });
      setSources((current) => (current ?? []).filter((item) => item.id !== source.id));
      clearSourceResults(source.id);
      setMessage(`${source.name} a été supprimé.`);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  if (sources === null) {
    return error !== '' ? <ErrorBox message={error} /> : <Loading />;
  }

  return (
    <>
      <PageHeader
        title="Scraping personnalisé"
        description="Ajoute les sites autorisés, détecte HTTP ou Browser et prévisualise avec Gemini les offres présentes dans le DOM public."
      />

      {message !== '' && <div className="notice">{message}</div>}
      {error !== '' && <ErrorBox message={error} />}
      <div style={{ height: 14 }} />

      <div className="grid cols-2">
        <Card>
          <h2 className="section-title">Ajouter un site</h2>
          <div className="stack">
            <label>
              Nom du site
              <input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Ex. Example Jobs" />
            </label>
            <label>
              URL de la liste des offres
              <input value={form.listingUrl} onChange={(event) => setForm({ ...form, listingUrl: event.target.value })} placeholder="https://jobs.example.com/offres" />
            </label>
            <label>
              URL d’une fiche offre, facultatif
              <input value={form.detailExampleUrl} onChange={(event) => setForm({ ...form, detailExampleUrl: event.target.value })} placeholder="https://jobs.example.com/offres/123" />
            </label>
            <label>
              Mode de récupération
              <select value={form.mode} onChange={(event) => setForm({ ...form, mode: event.target.value as ScraperMode })}>
                <option value="AUTO">Auto (recommandé)</option>
                <option value="HTTP">HTTP forcé</option>
                <option value="BROWSER">Browser / Playwright forcé</option>
              </select>
            </label>
            <div className="notice">
              <strong>Quel mode choisir ?</strong><br />
              Laisse <strong>Auto</strong>. Le test analyse une réponse HTTP. L’aperçu Gemini utilise ce DOM seulement s’il est exploitable ; si JavaScript est indispensable, JobPilot attend le rendu Browser/Playwright sans consommer de quota Gemini.
            </div>
            <div className="form-grid">
              <label>
                Fréquence (minutes)
                <input type="number" min="60" max="10080" value={form.syncIntervalMinutes} onChange={(event) => setForm({ ...form, syncIntervalMinutes: Number(event.target.value) })} />
              </label>
              <label>
                Pages max
                <input type="number" min="1" max="20" value={form.maxPages} onChange={(event) => setForm({ ...form, maxPages: Number(event.target.value) })} />
              </label>
              <label>
                Fiches détail max
                <input type="number" min="0" max="100" value={form.maxDetails} onChange={(event) => setForm({ ...form, maxDetails: Number(event.target.value) })} />
              </label>
            </div>
            <label>
              Date de vérification, facultatif
              <input type="date" value={form.authorizationCheckedAt} onChange={(event) => setForm({ ...form, authorizationCheckedAt: event.target.value })} />
            </label>
            <label>
              Référence / note CGU, facultatif
              <textarea value={form.authorizationReference} onChange={(event) => setForm({ ...form, authorizationReference: event.target.value })} placeholder="Lien ou note indiquant pourquoi tu considères la collecte autorisée." />
            </label>
            <label className="checkbox-label">
              <input type="checkbox" checked={form.authorizationConfirmed} onChange={(event) => setForm({ ...form, authorizationConfirmed: event.target.checked })} />
              Je confirme avoir vérifié que ce site autorise cette collecte automatisée.
            </label>
            <label className="checkbox-label">
              <input type="checkbox" checked={form.enabled} onChange={(event) => setForm({ ...form, enabled: event.target.checked })} />
              Activer cette source pour les synchronisations génériques.
            </label>
            <button className="btn" type="button" disabled={saving} onClick={() => void createSource()}>
              {saving ? 'Enregistrement…' : 'Ajouter la source'}
            </button>
          </div>
        </Card>

        <Card>
          <h2 className="section-title">Pipeline d’analyse</h2>
          <div className="stack">
            <p className="muted">Le moteur privilégie la solution la plus légère et réduit le contenu envoyé à Gemini.</p>
            <div className="notice"><strong>1. Probe HTTP</strong><br />Un téléchargement contrôlé avec robots.txt, timeout, quota et limite de taille.</div>
            <div className="notice"><strong>2. Détection</strong><br />HTTP si le DOM est exploitable ; Browser si la page dépend réellement de JavaScript.</div>
            <div className="notice"><strong>3. DOM compact</strong><br />Scripts, styles et bruit sont retirés ; le contenu est plafonné à 60 000 caractères et les JobPosting structurés sont conservés.</div>
            <div className="notice"><strong>4. Gemini</strong><br />Gemini renvoie un JSON structuré. JobPilot revalide les champs et refuse les URL qui quittent le domaine déclaré.</div>
            <div className="notice warning"><strong>Aperçu uniquement</strong><br />Cette étape ne crée encore aucune offre dans le catalogue et ne lance pas Playwright.</div>
          </div>
        </Card>
      </div>

      <div style={{ height: 18 }} />
      <Card>
        <h2 className="section-title">Sources enregistrées</h2>
        {sources.length === 0 ? (
          <p className="muted">Aucune source personnalisée pour le moment.</p>
        ) : (
          <div className="stack">
            {sources.map((source) => {
              const diagnostic = diagnostics[source.id];
              const preview = previews[source.id];
              return (
                <div className="notice" key={source.id}>
                  <div className="actions" style={{ justifyContent: 'space-between' }}>
                    <div><strong>{source.name}</strong> — <code>{source.domain}</code></div>
                    <div className="actions">
                      <Badge tone={source.enabled ? 'good' : 'warn'}>{source.enabled ? 'Actif' : 'Désactivé'}</Badge>
                      <Badge tone="blue">{modeLabel(source.mode)}</Badge>
                    </div>
                  </div>
                  <div style={{ marginTop: 8 }}><a href={source.listingUrl} target="_blank" rel="noreferrer">{source.listingUrl}</a></div>
                  <div className="muted" style={{ marginTop: 8 }}>
                    {intervalLabel(source.syncIntervalMinutes)} · {source.maxPages} pages max · {source.maxDetails} fiches max · autorisation vérifiée {source.authorizationCheckedAt ?? 'à la date d’ajout'}
                  </div>
                  {source.authorizationReference && <div className="muted" style={{ marginTop: 6 }}>{source.authorizationReference}</div>}
                  <div className="actions" style={{ marginTop: 10 }}>
                    <button className="btn" type="button" disabled={testingId !== null || extractingId !== null} onClick={() => void diagnoseSource(source)}>
                      {testingId === source.id ? 'Test en cours…' : 'Tester le site'}
                    </button>
                    <button className="btn" type="button" disabled={extractingId !== null || testingId !== null} onClick={() => void extractPreview(source)}>
                      {extractingId === source.id ? 'Analyse Gemini…' : 'Analyser avec Gemini'}
                    </button>
                    <button className="btn secondary" type="button" onClick={() => void patchSource(source, { enabled: !source.enabled })}>
                      {source.enabled ? 'Désactiver' : 'Activer'}
                    </button>
                    <select value={source.mode} onChange={(event) => void patchSource(source, { mode: event.target.value })} aria-label={`Mode ${source.name}`}>
                      <option value="AUTO">Auto</option>
                      <option value="HTTP">HTTP</option>
                      <option value="BROWSER">Browser</option>
                    </select>
                    <button className="btn secondary" type="button" onClick={() => void deleteSource(source)}>Supprimer</button>
                  </div>

                  {diagnostic && (
                    <div className="notice" style={{ marginTop: 12 }}>
                      <div className="actions">
                        <Badge tone={diagnostic.recommendedMode === 'HTTP' ? 'good' : 'warn'}>Recommandé : {diagnostic.recommendedMode}</Badge>
                        <Badge tone="blue">{confidenceLabel(diagnostic.confidence)}</Badge>
                        {source.mode !== 'AUTO' && <Badge tone="warn">Mode forcé : {diagnostic.effectiveMode}</Badge>}
                      </div>
                      <p style={{ marginBottom: 6 }}>{diagnostic.reason}</p>
                      <div className="muted">
                        HTTP {diagnostic.http.statusCode} · {diagnostic.http.responseBytes.toLocaleString('fr-FR')} octets · {diagnostic.signals.visibleTextCharacters.toLocaleString('fr-FR')} caractères visibles · {diagnostic.signals.jobLikeLinks} liens d’offres · {diagnostic.signals.jobStructuredData} JobPosting · {diagnostic.signals.javascriptMarkers} marqueurs JS
                      </div>
                      {diagnostic.browserVerificationRequired && (
                        <div className="muted" style={{ marginTop: 6 }}>Une vérification Browser/Playwright pourra confirmer ce résultat dans l’étape suivante.</div>
                      )}
                    </div>
                  )}

                  {preview && (
                    <div className={preview.requiresBrowser ? 'notice warning' : 'notice'} style={{ marginTop: 12 }}>
                      <div className="actions">
                        <Badge tone={preview.requiresBrowser ? 'warn' : 'good'}>Mode : {preview.effectiveMode}</Badge>
                        {preview.ai && <Badge tone="blue">Gemini : {preview.ai.model}</Badge>}
                        {preview.ai?.cacheHit && <Badge>Cache IA</Badge>}
                        {preview.ai && <Badge>Confiance extraction : {extractionConfidence(preview.ai.confidence)}</Badge>}
                      </div>
                      <p>{preview.message}</p>

                      {preview.requiresBrowser ? (
                        <div className="muted">Le DOM HTTP n’est pas suffisant. La prochaine étape ajoutera le rendu Playwright avant Gemini ; aucun appel Gemini n’a été effectué ici.</div>
                      ) : (
                        <>
                          {preview.dom && (
                            <div className="muted" style={{ marginBottom: 10 }}>
                              DOM : {preview.dom.originalBytes.toLocaleString('fr-FR')} octets → {preview.dom.compactedCharacters.toLocaleString('fr-FR')} caractères envoyables · {preview.dom.structuredDataBlocks} JobPosting structuré(s){preview.dom.truncated ? ' · contenu tronqué à la limite sûre' : ''}
                            </div>
                          )}
                          {preview.ai?.notes.map((note) => (
                            <div className="muted" key={note}>• {note}</div>
                          ))}
                          <div style={{ height: 8 }} />
                          {preview.offers.length === 0 ? (
                            <div className="muted">Gemini n’a identifié aucune offre suffisamment explicite dans ce DOM.</div>
                          ) : (
                            <div className="stack">
                              {preview.offers.map((offer, index) => {
                                const compensation = compensationLabel(offer);
                                return (
                                  <div className="list-row" key={`${offer.sourceUrl ?? offer.title}-${index}`}>
                                    <div style={{ flex: 1 }}>
                                      <strong>{offer.title}</strong>
                                      <div className="muted" style={{ marginTop: 4 }}>
                                        {offer.company ?? 'Entreprise non indiquée'}
                                        {offer.location ? ` · ${offer.location}` : ''}
                                        {offer.contractType ? ` · ${offer.contractType}` : ''}
                                        {offer.publishedAt ? ` · ${offer.publishedAt}` : ''}
                                      </div>
                                      <div className="actions" style={{ marginTop: 7 }}>
                                        {offer.workMode !== 'UNKNOWN' && <Badge>{offer.workMode}</Badge>}
                                        {compensation && <Badge tone="good">{compensation}</Badge>}
                                        {offer.technologies.slice(0, 10).map((technology) => <Badge key={technology}>{technology}</Badge>)}
                                      </div>
                                      {offer.description && <p className="muted" style={{ marginBottom: 4 }}>{offer.description}</p>}
                                      {offer.sourceUrl && <a href={offer.sourceUrl} target="_blank" rel="noreferrer">Voir l’offre source</a>}
                                    </div>
                                  </div>
                                );
                              })}
                            </div>
                          )}
                        </>
                      )}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </Card>
    </>
  );
}
