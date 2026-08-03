'use client';

import { useEffect, useState } from 'react';

import { Badge, Card, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Settings } from '@/lib/types';

type Source = { name: string; url: string; category: string; mode: string };

export default function SettingsPage() {
  const [settings, setSettings] = useState<Settings | null>(null);
  const [sources, setSources] = useState<Source[]>([]);
  const [gmail, setGmail] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  useEffect(() => {
    let active = true;

    void Promise.all([
      api<Settings>('/settings'),
      api<Source[]>('/settings/sources'),
      api<{ connected: boolean }>('/integrations/gmail/status'),
    ])
      .then(([loadedSettings, loadedSources, gmailStatus]) => {
        if (!active) return;
        setSettings(loadedSettings);
        setSources(loadedSources);
        setGmail(gmailStatus.connected);
      })
      .catch((caughtError: unknown) => {
        if (active) setError(getErrorMessage(caughtError));
      });

    return () => {
      active = false;
    };
  }, []);

  if (settings === null) {
    return error !== '' ? <ErrorBox message={error} /> : <Loading />;
  }

  const set = <K extends keyof Settings>(key: K, value: Settings[K]): void => {
    setSettings({ ...settings, [key]: value });
  };

  const save = async (): Promise<void> => {
    try {
      setSettings(await api<Settings>('/settings', {
        method: 'PUT',
        body: JSON.stringify(settings),
      }));
      setMessage('Paramètres enregistrés.');
      setError('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  const disconnectGmail = async (): Promise<void> => {
    try {
      await api('/integrations/gmail/disconnect', { method: 'POST' });
      setGmail(false);
      setError('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  return (
    <>
      <PageHeader
        title="Paramètres"
        description="Règles de recherche, score, rémunération et intégrations."
        actions={
          <button className="btn" type="button" onClick={() => void save()}>
            Enregistrer
          </button>
        }
      />
      {message !== '' && <div className="notice">{message}</div>}
      {error !== '' && <ErrorBox message={error} />}
      <div style={{ height: 14 }} />

      <div className="grid cols-2">
        <Card>
          <h2 className="section-title">Recherche et matching</h2>
          <div className="stack">
            <label>
              Postes ciblés (un par ligne)
              <textarea
                value={settings.targetJobs.join('\n')}
                onChange={(event) => set('targetJobs', event.target.value.split('\n').map((value) => value.trim()).filter(Boolean))}
              />
            </label>
            <label>
              Exclusions (une par ligne)
              <textarea
                value={settings.exclusions.join('\n')}
                onChange={(event) => set('exclusions', event.target.value.split('\n').map((value) => value.trim()).filter(Boolean))}
              />
            </label>
            <label>
              Compétences (une par ligne)
              <textarea
                value={settings.skills.join('\n')}
                onChange={(event) => set('skills', event.target.value.split('\n').map((value) => value.trim()).filter(Boolean))}
              />
            </label>
            <label>
              Seuil de préparation automatique
              <input
                type="number"
                min="0"
                max="100"
                value={settings.matchingThreshold}
                onChange={(event) => set('matchingThreshold', Number(event.target.value))}
              />
            </label>
            <label className="checkbox-label">
              <input
                type="checkbox"
                checked={settings.autoPrepare}
                onChange={(event) => set('autoPrepare', event.target.checked)}
              />
              Préparer automatiquement les offres éligibles
            </label>
          </div>
        </Card>

        <Card>
          <h2 className="section-title">Rémunération</h2>
          <div className="form-grid">
            <label>TJM Île-de-France<input type="number" value={settings.defaultIdfTjm} onChange={(e) => set('defaultIdfTjm', Number(e.target.value))} /></label>
            <label>TJM hors Île-de-France<input type="number" value={settings.defaultOutsideIdfTjm} onChange={(e) => set('defaultOutsideIdfTjm', Number(e.target.value))} /></label>
            <label>TJM remote sans lieu connu<input type="number" value={settings.defaultRemoteTjm} onChange={(e) => set('defaultRemoteTjm', Number(e.target.value))} /></label>
            <label>TJM minimum<input type="number" value={settings.minimumFreelanceTjm} onChange={(e) => set('minimumFreelanceTjm', Number(e.target.value))} /></label>
            <label>TJM maximum<input type="number" value={settings.maximumTjm} onChange={(e) => set('maximumTjm', Number(e.target.value))} /></label>
            <label>Salaire CDI minimum<input type="number" value={settings.minimumCdiSalary} onChange={(e) => set('minimumCdiSalary', Number(e.target.value))} /></label>
          </div>
          <div className="notice" style={{ marginTop: 14 }}>
            Fourchette ≤ 500 € : maximum. Au-dessus de 500 € : milieu de la fourchette, plafonné au TJM maximum. Le full remote suit la règle géographique lorsque le lieu est connu.
          </div>
        </Card>

        <Card>
          <h2 className="section-title">Gmail</h2>
          <p className="muted">Connexion OAuth en lecture seule. Aucun mot de passe n’est stocké.</p>
          <div className="actions">
            {gmail ? (
              <>
                <Badge tone="good">Connecté</Badge>
                <button className="btn secondary" type="button" onClick={() => void disconnectGmail()}>
                  Déconnecter
                </button>
              </>
            ) : (
              <a className="btn" href="/api/integrations/gmail/start">Connecter Gmail</a>
            )}
          </div>
        </Card>

        <Card>
          <h2 className="section-title">Finalisation des candidatures</h2>
          <label>
            Parcours de finalisation
            <select
              value={settings.finalSubmissionMode}
              onChange={(event) => set('finalSubmissionMode', event.target.value)}
            >
              <option value="ONE_CLICK">Guidé : ouvrir la plateforme puis confirmer dans JobPilot</option>
              <option value="PREPARE_ONLY">Préparer uniquement, sans suivi de l’envoi</option>
              <option value="AUTOMATIC_AUTHORIZED_ONLY">Automatique uniquement lorsqu’une API officielle le permet</option>
            </select>
          </label>
          <div className="notice warning" style={{ marginTop: 14 }}>
            <strong>Fonctionnement actuel :</strong> JobPilot prépare les éléments, mais ne soumet pas les formulaires externes. L’envoi se fait sur la plateforme d’origine, puis tu le confirmes dans JobPilot. Les CAPTCHA et protections anti-bot ne sont jamais contournés.
          </div>
        </Card>
      </div>

      <div style={{ height: 18 }} />
      <Card>
        <h2 className="section-title">Plateformes suivies</h2>
        <div className="table-wrap">
          <table className="table">
            <thead><tr><th>Plateforme</th><th>Catégorie</th><th>Mode</th><th /></tr></thead>
            <tbody>
              {sources.map((source) => (
                <tr key={source.name}>
                  <td><strong>{source.name}</strong></td>
                  <td><Badge>{source.category}</Badge></td>
                  <td>{source.mode}</td>
                  <td>
                    <a className="btn secondary small" href={source.url} target="_blank" rel="noreferrer">
                      Ouvrir
                    </a>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </>
  );
}
