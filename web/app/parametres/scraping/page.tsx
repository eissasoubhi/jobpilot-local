'use client';

import { useEffect, useState } from 'react';

import { Badge, Card, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

type ScraperMode = 'AUTO' | 'HTTP' | 'BROWSER';

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

export default function CustomScrapingSettingsPage() {
  const [sources, setSources] = useState<CustomScraperSource[] | null>(null);
  const [form, setForm] = useState<NewSourceForm>(initialForm);
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
      setMessage(`${updated.name} a été mis à jour.`);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  const deleteSource = async (source: CustomScraperSource): Promise<void> => {
    if (!window.confirm(`Supprimer ${source.name} du registre de scraping ?`)) return;

    setError('');
    setMessage('');
    try {
      await api<void>(`/custom-scrapers/${source.id}`, { method: 'DELETE' });
      setSources((current) => (current ?? []).filter((item) => item.id !== source.id));
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
        description="Ajoute les sites dont tu as vérifié l’autorisation de collecte. JobPilot enregistrera leur mode et leurs limites avant toute exécution."
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
              Laisse <strong>Auto</strong> dans la majorité des cas. JobPilot essaiera d’abord HTTP. Si les offres ne sont disponibles qu’après exécution JavaScript, le moteur pourra basculer vers Browser/Playwright. Force HTTP pour un site HTML classique ; force Browser uniquement pour un site réellement rendu côté navigateur.
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
            <p className="muted">
              Le moteur générique privilégie toujours la solution la plus légère. Une page HTML exploitable n’a aucune raison de lancer Chromium.
            </p>
            <div className="notice"><strong>1. HTTP</strong><br />Téléchargement contrôlé du HTML, puis recherche des offres dans le DOM reçu.</div>
            <div className="notice"><strong>2. Vérification</strong><br />Si le DOM contient suffisamment de contenu exploitable, HTTP reste le mode retenu.</div>
            <div className="notice"><strong>3. Browser</strong><br />Si la page dépend de JavaScript pour faire apparaître les offres, Browser/Playwright devient nécessaire.</div>
            <div className="notice warning"><strong>Important</strong><br />Le choix Browser ne doit pas servir à contourner une authentification, un CAPTCHA ou une restriction d’accès.</div>
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
            {sources.map((source) => (
              <div className="notice" key={source.id}>
                <div className="actions" style={{ justifyContent: 'space-between' }}>
                  <div>
                    <strong>{source.name}</strong> — <code>{source.domain}</code>
                  </div>
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
              </div>
            ))}
          </div>
        )}
      </Card>
    </>
  );
}
