'use client';

import Link from 'next/link';
import { type FormEvent, useCallback, useEffect, useMemo, useState } from 'react';

import { OfferApplicationSummary } from '@/components/OfferApplicationSummary';
import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import { matchesOfferInboxView, type OfferInboxView } from '@/lib/offer-inbox';
import type { Application, Job, JobSourceOccurrence } from '@/lib/types';

type JobForm = {
  source: string;
  sourceUrl: string;
  title: string;
  company: string;
  clientName: string;
  location: string;
  contractType: string;
  workMode: string;
  description: string;
  publishedAt: string;
  salaryMin: string;
  salaryMax: string;
  tjmFixed: string;
  tjmMin: string;
  tjmMax: string;
};

type ProviderSync = {
  code?: string;
  name: string;
  mode?: string;
  configured?: boolean;
  enabled?: boolean;
  received?: number;
  imported?: number;
  merged?: number;
  duplicates?: number;
  failed?: number;
  error?: string | null;
};

type SyncResult = {
  configured: boolean;
  providers: ProviderSync[];
  lastSyncedAt: string | null;
  nextSyncAt: string | null;
  due: boolean;
  busy?: boolean;
  skipped?: boolean;
  message?: string;
  received?: number;
  imported?: number;
  merged?: number;
  duplicates?: number;
  failed?: number;
  errors?: string[];
};

const initialForm: JobForm = {
  source: 'Manuel',
  sourceUrl: '',
  title: '',
  company: '',
  clientName: '',
  location: '',
  contractType: 'CDI',
  workMode: 'Hybride',
  description: '',
  publishedAt: '',
  salaryMin: '',
  salaryMax: '',
  tjmFixed: '',
  tjmMin: '',
  tjmMax: '',
};

function tone(status: string): 'good' | 'warn' | 'bad' | 'blue' | 'neutral' {
  if (status === 'PREPARED') return 'good';
  if (status === 'REJECTED_BY_FILTER') return 'bad';
  if (status === 'MATCHED') return 'blue';
  return 'neutral';
}

function age(job: Job): string {
  if (job.ageHours == null) return 'Date inconnue';
  if (job.ageHours < 24) return `Il y a ${job.ageHours} h`;
  return `Il y a ${Math.floor(job.ageHours / 24)} j`;
}

function nullableNumber(value: string): number | null {
  return value === '' ? null : Number(value);
}

function formatDate(value: string | null | undefined): string {
  if (!value) return 'Jamais';

  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value));
}

function occurrences(job: Job): JobSourceOccurrence[] {
  if (job.sources && job.sources.length > 0) return job.sources;

  return [{
    id: null,
    sourceCode: job.sourceCode || job.source.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
    sourceName: job.source,
    externalId: null,
    sourceUrl: job.sourceUrl || null,
    matchType: 'LEGACY',
    matchScore: 100,
    matchReasons: [],
    publishedAt: job.publishedAt || null,
    firstSeenAt: job.publishedAt || new Date().toISOString(),
    lastSeenAt: job.publishedAt || new Date().toISOString(),
  }];
}

function matchLabel(matchType: string): string {
  return {
    PRIMARY: 'Source principale',
    EXACT_SOURCE_ID: 'Occurrence déjà connue',
    EXACT_URL: 'Fusion par URL',
    EXACT_FINGERPRINT: 'Fusion exacte',
    SIMILARITY: 'Fusion par similarité',
    LEGACY: 'Source historique',
  }[matchType] ?? matchType;
}

export default function JobsPage() {
  const [jobs, setJobs] = useState<Job[] | null>(null);
  const [applications, setApplications] = useState<Application[] | null>(null);
  const [form, setForm] = useState<JobForm>(initialForm);
  const [error, setError] = useState('');
  const [show, setShow] = useState(false);
  const [filter, setFilter] = useState('all');
  const [inboxView, setInboxView] = useState<OfferInboxView>('actionable');
  const [sourceFilter, setSourceFilter] = useState('all');
  const [syncing, setSyncing] = useState(false);
  const [syncInfo, setSyncInfo] = useState<SyncResult | null>(null);

  const loadJobs = useCallback(async (): Promise<void> => {
    try {
      const result = await api<Job[]>('/jobs');
      setJobs(result);
    } catch (caughtError: unknown) {
      setJobs((current) => current ?? []);
      setError(getErrorMessage(caughtError));
    }
  }, []);

  const loadApplications = useCallback(async (): Promise<void> => {
    try {
      const result = await api<Application[]>('/applications');
      setApplications(result);
    } catch (caughtError: unknown) {
      setApplications((current) => current ?? []);
      setError(`Les offres restent disponibles, mais les préparations de candidature sont indisponibles : ${getErrorMessage(caughtError)}`);
    }
  }, []);

  const refreshWorkspace = useCallback(async (): Promise<void> => {
    await Promise.all([loadJobs(), loadApplications()]);
  }, [loadApplications, loadJobs]);

  const syncJobs = useCallback(async (force: boolean): Promise<void> => {
    setSyncing(true);
    if (force) setError('');

    try {
      const result = await api<SyncResult>(`/job-search/sync${force ? '?force=1' : ''}`, {
        method: 'POST',
      });
      setSyncInfo(result);

      // Keep the already rendered local catalog visible while the refreshed catalog
      // is fetched. New or updated offers replace the list only when the request ends.
      await loadJobs();
      void loadApplications();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSyncing(false);
    }
  }, [loadApplications, loadJobs]);

  useEffect(() => {
    let active = true;

    void (async () => {
      // The local catalog is the first paint. Applications and connector sync are
      // intentionally started only after those already synchronized offers render.
      await loadJobs();
      if (!active) return;

      void loadApplications();
      void syncJobs(false);
    })();

    return () => {
      active = false;
    };
  }, [loadApplications, loadJobs, syncJobs]);

  const submit = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
    event.preventDefault();
    setError('');

    try {
      await api('/jobs', {
        method: 'POST',
        body: JSON.stringify({
          ...form,
          salaryMin: nullableNumber(form.salaryMin),
          salaryMax: nullableNumber(form.salaryMax),
          tjmFixed: nullableNumber(form.tjmFixed),
          tjmMin: nullableNumber(form.tjmMin),
          tjmMax: nullableNumber(form.tjmMax),
          proposedTjm: undefined,
          publishedAt: form.publishedAt || null,
        }),
      });
      setForm(initialForm);
      setShow(false);
      await refreshWorkspace();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  const prepare = async (id: number): Promise<void> => {
    try {
      await api(`/jobs/${id}/prepare`, { method: 'POST' });
      await refreshWorkspace();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  const updateApplication = useCallback((updated: Application): void => {
    setApplications((current) => current?.map((application) => (
      application.id === updated.id ? updated : application
    )) ?? current);
  }, []);

  const sources = useMemo(
    () => Array.from(new Set(
      (jobs ?? []).flatMap((job) => occurrences(job).map((source) => source.sourceName)).filter(Boolean),
    )).sort((a, b) => a.localeCompare(b, 'fr')),
    [jobs],
  );

  const applicationsByJobId = useMemo(
    () => new Map((applications ?? []).map((application) => [application.jobOffer.id, application])),
    [applications],
  );

  const displayed = useMemo(
    () => jobs?.filter((job) => (
      (filter === 'all' || job.status === filter)
      && (sourceFilter === 'all' || occurrences(job).some((source) => source.sourceName === sourceFilter))
      && matchesOfferInboxView(applicationsByJobId.get(job.id), inboxView)
    )) ?? [],
    [jobs, filter, sourceFilter, applicationsByJobId, inboxView],
  );

  const providerNames = syncInfo?.providers
    .filter((provider) => provider.configured !== false && provider.enabled !== false)
    .map((provider) => provider.name)
    .join(', ');

  return (
    <>
      <PageHeader
        title="Offres"
        description="Examine l’offre, son score et les éléments de candidature déjà préparés depuis un seul espace."
        actions={
          <div className="actions">
            <Link className="btn secondary" href="/connecteurs">Gérer les connecteurs</Link>
            <button
              className="btn secondary"
              type="button"
              disabled={syncing}
              onClick={() => void syncJobs(true)}
            >
              {syncing ? 'Recherche en cours…' : 'Rechercher maintenant'}
            </button>
            <button className="btn" type="button" onClick={() => setShow(true)}>
              Ajouter une offre
            </button>
          </div>
        }
      />
      {error !== '' && <ErrorBox message={error} />}

      <Card>
        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 16, alignItems: 'flex-start', flexWrap: 'wrap' }}>
          <div>
            <div className="actions" style={{ alignItems: 'center' }}>
              <strong>Recherche automatique</strong>
              <Badge tone={syncing ? 'blue' : 'good'}>
                {syncing ? 'Mise à jour en arrière-plan' : 'Données locales affichées'}
              </Badge>
              {applications === null && <Badge>Suivi candidatures en cours…</Badge>}
            </div>
            <div className="muted small" style={{ marginTop: 7 }}>
              {syncing
                ? 'Les offres déjà synchronisées restent visibles pendant que JobPilot consulte les connecteurs actifs, normalise et fusionne les nouvelles occurrences.'
                : syncInfo?.message ?? 'Les offres locales sont affichées en premier. La recherche automatique complète ensuite la liste sans bloquer la page.'}
            </div>
          </div>
          <div className="small muted">
            Dernière recherche : <strong>{formatDate(syncInfo?.lastSyncedAt)}</strong>
          </div>
        </div>

        {syncInfo && (
          <div className="actions" style={{ marginTop: 12 }}>
            <Badge tone="blue">Sources : {providerNames || 'aucune'}</Badge>
            {syncInfo.imported != null && <Badge tone="good">{syncInfo.imported} nouvelle(s)</Badge>}
            {syncInfo.merged != null && <Badge tone="blue">{syncInfo.merged} source(s) fusionnée(s)</Badge>}
            {syncInfo.duplicates != null && <Badge>{syncInfo.duplicates} occurrence(s) connue(s)</Badge>}
            {syncInfo.failed != null && syncInfo.failed > 0 && <Badge tone="warn">{syncInfo.failed} échec(s)</Badge>}
          </div>
        )}

        {syncInfo?.errors && syncInfo.errors.length > 0 && (
          <details style={{ marginTop: 10 }}>
            <summary className="small muted">Détails des sources indisponibles</summary>
            <ul>
              {syncInfo.errors.map((syncError) => <li className="small" key={syncError}>{syncError}</li>)}
            </ul>
          </details>
        )}

        <p className="small muted" style={{ marginBottom: 0, marginTop: 12 }}>
          Une nouvelle plateforme ajoute une occurrence à l’offre existante lorsqu’URL, entreprise et intitulé correspondent avec une confiance suffisante.
        </p>
      </Card>

      <Card>
        <label style={{ maxWidth: 360 }}>
          Filtrer par source
          <select
            aria-label="Filtrer par source"
            value={sourceFilter}
            onChange={(event) => setSourceFilter(event.target.value)}
          >
            <option value="all">Toutes les sources</option>
            {sources.map((source) => <option key={source} value={source}>{source}</option>)}
          </select>
        </label>
      </Card>

      <div className="tabs" aria-label="Boîte des offres">
        {[
          ['actionable', 'À traiter'],
          ['submitted', 'Envoyées'],
          ['ignored', 'Ignorées'],
        ].map(([value, label]) => (
          <button
            key={value}
            className={inboxView === value ? 'active' : ''}
            type="button"
            onClick={() => setInboxView(value as OfferInboxView)}
          >
            {label}
          </button>
        ))}
      </div>

      <div className="tabs" aria-label="Filtres des offres">
        {[
          ['all', 'Toutes'],
          ['PREPARED', 'Préparées'],
          ['MATCHED', 'À examiner'],
          ['REJECTED_BY_FILTER', 'Exclues'],
        ].map(([value, label]) => (
          <button
            key={value}
            className={filter === value ? 'active' : ''}
            type="button"
            onClick={() => setFilter(value)}
          >
            {label}
          </button>
        ))}
      </div>

      <Card>
        {jobs === null ? (
          <Loading />
        ) : displayed.length === 0 ? (
          <Empty>Aucune offre ne correspond aux filtres sélectionnés.</Empty>
        ) : (
          displayed.map((job) => {
            const jobOccurrences = occurrences(job);
            const application = applicationsByJobId.get(job.id);

            return (
              <div className="list-row" key={job.id}>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div className="actions" style={{ marginBottom: 6 }}>
                    <Badge tone={tone(job.status)}>{job.status}</Badge>
                    <Badge tone="blue">{job.language === 'fr' ? 'FR' : 'EN'}</Badge>
                    <Badge>{job.contractType || 'Contrat inconnu'}</Badge>
                    <Badge tone={jobOccurrences.length > 1 ? 'blue' : 'neutral'}>
                      {jobOccurrences.length} source{jobOccurrences.length > 1 ? 's' : ''}
                    </Badge>
                    {jobOccurrences.slice(0, 4).map((source) => (
                      <Badge key={`${source.sourceCode}-${source.externalId || source.sourceUrl || source.sourceName}`}>
                        {source.sourceName}
                      </Badge>
                    ))}
                    {jobOccurrences.length > 4 && <Badge>+{jobOccurrences.length - 4}</Badge>}
                    {job.proposedTjm != null && <Badge tone="good">TJM proposé : {job.proposedTjm} €</Badge>}
                    {job.proposedSalary != null && (
                      <Badge tone="good">Salaire proposé : {job.proposedSalary.toLocaleString('fr-FR')} €</Badge>
                    )}
                  </div>
                  <h3>{job.title}</h3>
                  <div className="muted small">
                    {job.company || 'Entreprise non renseignée'} · {job.location || 'Lieu non renseigné'} · {age(job)}
                  </div>
                  {job.recommendedCv && (
                    <div className="small" style={{ marginTop: 7 }}>
                      CV conseillé : <strong>{job.recommendedCv.name}</strong>
                    </div>
                  )}
                  {application && (
                    <OfferApplicationSummary
                      application={application}
                      onApplicationUpdated={updateApplication}
                    />
                  )}
                  <details style={{ marginTop: 8 }}>
                    <summary className="small muted">Pourquoi ce score ?</summary>
                    <ul>{(job.scoreReasons ?? []).map((reason) => <li key={reason} className="small">{reason}</li>)}</ul>
                  </details>
                  <details style={{ marginTop: 8 }}>
                    <summary className="small muted">
                      Sources de cette offre ({jobOccurrences.length})
                    </summary>
                    <div className="stack" style={{ gap: 8, marginTop: 10 }}>
                      {jobOccurrences.map((source) => (
                        <div className="notice" key={`${source.sourceCode}-${source.externalId || source.sourceUrl || source.sourceName}`}>
                          <div className="actions">
                            <strong>{source.sourceName}</strong>
                            <Badge tone={source.matchType === 'PRIMARY' || source.matchType === 'LEGACY' ? 'neutral' : 'blue'}>
                              {matchLabel(source.matchType)}
                            </Badge>
                            {source.matchType !== 'PRIMARY' && source.matchType !== 'LEGACY' && (
                              <Badge>{source.matchScore} %</Badge>
                            )}
                          </div>
                          {source.matchReasons.length > 0 && (
                            <div className="small muted" style={{ marginTop: 6 }}>
                              {source.matchReasons.join(' ')}
                            </div>
                          )}
                          {source.sourceUrl && (
                            <a
                              className="btn secondary small"
                              href={source.sourceUrl}
                              target="_blank"
                              rel="noreferrer"
                              style={{ marginTop: 8 }}
                            >
                              Ouvrir sur {source.sourceName}
                            </a>
                          )}
                        </div>
                      ))}
                    </div>
                  </details>
                  <div className="actions" style={{ marginTop: 10 }}>
                    {job.sourceUrl && (
                      <a className="btn secondary small" href={job.sourceUrl} target="_blank" rel="noreferrer">
                        Ouvrir la source principale
                      </a>
                    )}
                    {job.status !== 'PREPARED' && job.status !== 'REJECTED_BY_FILTER' && (
                      <button className="btn small" type="button" onClick={() => void prepare(job.id)}>
                        Préparer
                      </button>
                    )}
                  </div>
                </div>
                <div className="score" aria-label={`Score ${job.score}`}>{job.score}</div>
              </div>
            );
          })
        )}

        {jobs?.some((job) => occurrences(job).some((source) => source.sourceName === 'Adzuna')) && (
          <p className="small muted" style={{ marginBottom: 0, marginTop: 16 }}>
            Jobs by <a href="https://www.adzuna.fr" target="_blank" rel="noreferrer">Adzuna</a>
          </p>
        )}
      </Card>

      {show && (
        <div className="modal-backdrop" onMouseDown={() => setShow(false)}>
          <div
            className="modal"
            role="dialog"
            aria-modal="true"
            aria-label="Ajouter une offre"
            onMouseDown={(event) => event.stopPropagation()}
          >
            <PageHeader
              title="Ajouter une offre"
              actions={<button className="btn secondary" type="button" onClick={() => setShow(false)}>Fermer</button>}
            />
            <form className="form-grid" onSubmit={(event) => void submit(event)}>
              <label>Source<input value={form.source} onChange={(e) => setForm({ ...form, source: e.target.value })} /></label>
              <label>URL<input value={form.sourceUrl} onChange={(e) => setForm({ ...form, sourceUrl: e.target.value })} /></label>
              <label>Intitulé<input required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} /></label>
              <label>Entreprise<input value={form.company} onChange={(e) => setForm({ ...form, company: e.target.value })} /></label>
              <label>Client final éventuel<input value={form.clientName} onChange={(e) => setForm({ ...form, clientName: e.target.value })} /></label>
              <label>Lieu<input value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })} /></label>
              <label>
                Contrat
                <select value={form.contractType} onChange={(e) => setForm({ ...form, contractType: e.target.value })}>
                  <option>CDI</option><option>CDD</option><option>Freelance</option><option>Portage salarial</option><option>Sous-traitance</option>
                </select>
              </label>
              <label>Mode de travail<input value={form.workMode} onChange={(e) => setForm({ ...form, workMode: e.target.value })} /></label>
              <label>Date de publication<input type="datetime-local" value={form.publishedAt} onChange={(e) => setForm({ ...form, publishedAt: e.target.value })} /></label>
              <label>Salaire min. annuel<input type="number" value={form.salaryMin} onChange={(e) => setForm({ ...form, salaryMin: e.target.value })} /></label>
              <label>Salaire max. annuel<input type="number" value={form.salaryMax} onChange={(e) => setForm({ ...form, salaryMax: e.target.value })} /></label>
              <label>TJM fixe<input type="number" value={form.tjmFixed} onChange={(e) => setForm({ ...form, tjmFixed: e.target.value })} /></label>
              <label>TJM minimum<input type="number" value={form.tjmMin} onChange={(e) => setForm({ ...form, tjmMin: e.target.value })} /></label>
              <label>TJM maximum<input type="number" value={form.tjmMax} onChange={(e) => setForm({ ...form, tjmMax: e.target.value })} /></label>
              <label className="full">Description<textarea required value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></label>
              <button className="btn full" type="submit">Analyser et enregistrer</button>
            </form>
          </div>
        </div>
      )}
    </>
  );
}
