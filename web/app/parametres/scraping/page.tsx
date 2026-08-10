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
  http: {
    requestedUrl: string;
    finalUrl: string;
    statusCode: number;
    contentType: string | null;
    responseBytes: number;
    networkRequests: number;
    fromCache: boolean;
  };
};

type ExtractionQuality = {
  reliable: boolean;
  score: number;
  reasons: string[];
};

type ScrapedCandidate = {
  sourceUrl: string;
  externalId: string;
  title: string;
  company: string;
  location: string;
  contractType: string;
  workMode: string;
  language: string;
  description: string;
  publishedAt: string | null;
  salaryMin: number | null;
  salaryMax: number | null;
  tjmMin: number | null;
  tjmMax: number | null;
  rawData: Record<string, unknown>;
};

type ScraperPreview = {
  configuredMode: ScraperMode;
  recommendedMode: Exclude<ScraperMode, 'AUTO'>;
  effectiveMode: Exclude<ScraperMode, 'AUTO'>;
  requiresBrowser: boolean;
  candidateCount: number;
  reliableCount: number;
  detailLimit: number;
  detailEnriched: number;
  detailError: string | null;
  candidates: ScrapedCandidate[];
  signals: Record<string, unknown>;
  http: {
    requestedUrl: string;
    finalUrl: string;
    statusCode: number;
    responseBytes: number;
    networkRequests: number;
    fromCache: boolean;
  };
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

function tjmLabel(candidate: ScrapedCandidate): string | null {
  if (candidate.tjmMin === null && candidate.tjmMax === null) return null;
  if (candidate.tjmMin !== null && candidate.tjmMax !== null && candidate.tjmMin !== candidate.tjmMax) {
    return `TJM ${candidate.tjmMin}–${candidate.tjmMax} €`;
  }
  return `TJM ${candidate.tjmMin ?? candidate.tjmMax} €`;
}

function extractionLabel(candidate: ScrapedCandidate): string {
  const detailMethod = typeof candidate.rawData.detailExtractionMethod === 'string'
    ? candidate.rawData.detailExtractionMethod
    : null;
  const method = detailMethod ?? (typeof candidate.rawData.extractionMethod === 'string' ? candidate.rawData.extractionMethod : 'HTTP');

  if (method === 'JSON_LD') return 'JobPosting';
  if (method === 'DOM') return 'DOM détail';
  if (method === 'JOB_LINK') return 'Lien détecté';
  return method;
}

function extractionQuality(candidate: ScrapedCandidate): ExtractionQuality | null {
  const value = candidate.rawData.quality;
  if (typeof value !== 'object' || value === null || Array.isArray(value)) return null;

  const quality = value as Record<string, unknown>;
  const score = typeof quality.score === 'number' ? quality.score : null;
  const reliable = typeof quality.reliable === 'boolean' ? quality.reliable : null;
  const reasons = Array.isArray(quality.reasons)
    ? quality.reasons.filter((reason): reason is string => typeof reason === 'string')
    : [];

  if (score === null || reliable === null) return null;
  return { reliable, score, reasons };
}

export default function CustomScrapingSettingsPage() {
  const [sources, setSources] = useState<CustomScraperSource[] | null>(null);
  const [form, setForm] = useState<NewSourceForm>(initialForm);
  const [diagnostics, setDiagnostics] = useState<Record<number, ScraperDiagnostic>>({});
  const [previews, setPreviews] = useState<Record<number, ScraperPreview>>({});
  const [testingId, setTestingId] = useState<number | null>(null);
  const [previewingId, setPreviewingId] = useState<number | null>(null);
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

  const patchSource = async (source: CustomScraperSource, patch: Record<string, unknown>): Promise<void> => {
    setError('');
    setMessage('');
    try {
      const updated = await api<CustomScraperSource>(`/custom-scrapers/${source.id}`, {
        method: 'PATCH',
        body: JSON.stringify(patch),
      });
      setSources((current) => (current ?? []).map((item) => item.id === updated.id ? updated : item));
      setDiagnostics((current) => {
        const next = { ...current };
        delete next[source.id];
        return next;
      });
      setPreviews((current) => {
        const next = { ...current };
        delete next[source.id];
        return next;
      });
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

  const previewSource = async (source: CustomScraperSource): Promise<void> => {
    setPreviewingId(source.id);
    setError('');
    setMessage('');
    try {
      const result = await api<ScraperPreview>(`/custom-scrapers/${source.id}/preview`, { method: 'POST' });
      setPreviews((current) => ({ ...current, [source.id]: result }));
      setMessage(`${result.candidateCount} candidat(s) détecté(s), dont ${result.reliableCount} extraction(s) fiable(s) pour ${source.name}.`);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setPreviewingId(null);
    }
  };

  const deleteSource = async (source: CustomScraperSource): Promise<void> => {
    if (!window.confirm(`Supprimer ${source.name} du registre de scraping ?`)) return;

    setError('');
    setMessage('');
    try {
      await api<void>(`/custom-scrapers/${source.id}`, { method: 'DELETE' });
      setSources((current) => (current ?? []).filter((item) => item.id !== source.id));
      setDiagnostics((current) => {
        const next = { ...current };
        delete next[source.id];
        return next;
      });
      setPreviews((current) => {
        const next = { ...current };
        delete next[source.id];
        return next;
      });
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
        description="Ajoute les sites dont tu as vérifié l’autorisation de collecte, puis teste et prévisualise les offres publiques avant toute importation."
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
              Laisse <strong>Auto</strong>. Après l’ajout, <strong>Tester le site</strong> analyse le rendu disponible et <strong>Prévisualiser les offres</strong> montre ce que JobPilot peut réellement extraire sans enregistrer de candidature.
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
          <h2 className="section-title">Comment fonctionne Auto ?</h2>
          <div className="stack">
            <p className="muted">Le moteur privilégie toujours la solution la plus légère et ne lance pas Chromium si le HTML serveur suffit.</p>
            <div className="notice"><strong>1. Probe HTTP</strong><br />Un téléchargement contrôlé, avec robots.txt, timeout, quota et limite de taille.</div>
            <div className="notice"><strong>2. Signaux</strong><br />JobPosting, liens d’offres, texte visible, scripts et marqueurs React/Next/Nuxt sont comparés.</div>
            <div className="notice"><strong>3. Prévisualisation</strong><br />Les candidats HTTP sont affichés sans persistance ; quelques fiches détail peuvent être enrichies de façon bornée.</div>
            <div className="notice warning"><strong>Important</strong><br />Le diagnostic ne contourne ni authentification, ni CAPTCHA, ni restriction d’accès. Browser n’est pas encore exécuté dans cette étape.</div>
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
                    <button className="btn" type="button" disabled={testingId !== null || previewingId !== null} onClick={() => void diagnoseSource(source)}>
                      {testingId === source.id ? 'Test en cours…' : 'Tester le site'}
                    </button>
                    <button className="btn secondary" type="button" disabled={testingId !== null || previewingId !== null} onClick={() => void previewSource(source)}>
                      {previewingId === source.id ? 'Prévisualisation…' : 'Prévisualiser les offres'}
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
                    <div className="notice" style={{ marginTop: 12 }}>
                      <div className="actions">
                        <Badge tone={preview.requiresBrowser ? 'warn' : 'blue'}>{preview.candidateCount} candidat(s)</Badge>
                        <Badge tone={preview.reliableCount > 0 ? 'good' : 'warn'}>{preview.reliableCount} éligible(s) à l’import</Badge>
                        <Badge tone="blue">Mode : {preview.effectiveMode}</Badge>
                        {preview.detailLimit > 0 && <Badge tone="blue">{preview.detailEnriched}/{preview.detailLimit} fiche(s) enrichie(s)</Badge>}
                      </div>
                      <div className="muted" style={{ marginTop: 6 }}>
                        {preview.http.networkRequests} requête(s) cible · HTTP {preview.http.statusCode} · aucune offre enregistrée
                      </div>
                      <div className="muted" style={{ marginTop: 6 }}>
                        « Fiable » signifie que l’extraction est assez complète pour entrer dans le pipeline JobPilot ; ce n’est pas le score de compatibilité avec ton profil.
                      </div>
                      {preview.requiresBrowser && (
                        <div className="notice warning" style={{ marginTop: 10 }}>Le HTML serveur ne suffit pas. Cette source devra passer par le worker Browser/Playwright lorsqu’il sera disponible.</div>
                      )}
                      {preview.detailError && (
                        <div className="notice warning" style={{ marginTop: 10 }}>Enrichissement arrêté : {preview.detailError}</div>
                      )}
                      {preview.candidates.length === 0 && !preview.requiresBrowser && (
                        <p className="muted" style={{ marginTop: 10 }}>Aucune offre suffisamment identifiable dans cette réponse HTTP.</p>
                      )}
                      {preview.candidates.length > 0 && (
                        <div className="stack" style={{ marginTop: 12 }}>
                          {preview.candidates.slice(0, 10).map((candidate) => {
                            const details = [candidate.company, candidate.contractType, candidate.location, candidate.workMode, tjmLabel(candidate)]
                              .filter((value): value is string => Boolean(value));
                            const description = candidate.description.trim();
                            const shortDescription = description.length > 320 ? `${description.slice(0, 320)}…` : description;
                            const quality = extractionQuality(candidate);

                            return (
                              <div className="notice" key={candidate.externalId}>
                                <div className="actions" style={{ justifyContent: 'space-between' }}>
                                  <strong>{candidate.title}</strong>
                                  <div className="actions">
                                    <Badge tone="blue">{extractionLabel(candidate)}</Badge>
                                    {quality && (
                                      <Badge tone={quality.reliable ? 'good' : 'warn'}>
                                        {quality.reliable ? 'Fiable' : 'À vérifier'} · {quality.score}/100
                                      </Badge>
                                    )}
                                  </div>
                                </div>
                                {details.length > 0 && <div className="muted" style={{ marginTop: 6 }}>{details.join(' · ')}</div>}
                                {shortDescription !== '' && <p style={{ marginBottom: 6 }}>{shortDescription}</p>}
                                {quality && quality.reasons.length > 0 && (
                                  <div className="muted" style={{ marginBottom: 6 }}>Qualité : {quality.reasons.slice(0, 3).join(' · ')}</div>
                                )}
                                <a href={candidate.sourceUrl} target="_blank" rel="noreferrer">Voir la fiche source</a>
                              </div>
                            );
                          })}
                          {preview.candidateCount > 10 && (
                            <div className="muted">{preview.candidateCount - 10} autre(s) candidat(s) détecté(s), masqué(s) dans cette prévisualisation compacte.</div>
                          )}
                        </div>
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
