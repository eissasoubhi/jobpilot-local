'use client';

import { useEffect, useState } from 'react';

import { Badge, Card, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

type AiSettings = {
  provider: 'gemini';
  enabled: boolean;
  model: string;
  apiKeyConfigured: boolean;
  apiKeySource: 'interface' | 'environment' | 'none';
  hasInterfaceOverrides: boolean;
};

function sourceLabel(source: AiSettings['apiKeySource']): string {
  if (source === 'interface') return 'Clé enregistrée dans JobPilot';
  if (source === 'environment') return 'Clé fournie par .env';
  return 'Aucune clé configurée';
}

export default function IntegrationSettingsPage() {
  const [settings, setSettings] = useState<AiSettings | null>(null);
  const [enabled, setEnabled] = useState(false);
  const [model, setModel] = useState('gemini-3.5-flash-lite');
  const [apiKey, setApiKey] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  useEffect(() => {
    let active = true;

    void api<AiSettings>('/settings/ai')
      .then((response) => {
        if (!active) return;
        setSettings(response);
        setEnabled(response.enabled);
        setModel(response.model);
      })
      .catch((caughtError: unknown) => {
        if (active) setError(getErrorMessage(caughtError));
      });

    return () => {
      active = false;
    };
  }, []);

  const save = async (): Promise<void> => {
    setSaving(true);
    setError('');
    setMessage('');

    try {
      const payload: Record<string, unknown> = { enabled, model };
      if (apiKey.trim() !== '') payload.apiKey = apiKey.trim();

      const response = await api<AiSettings>('/settings/ai', {
        method: 'PUT',
        body: JSON.stringify(payload),
      });
      setSettings(response);
      setEnabled(response.enabled);
      setModel(response.model);
      setApiKey('');
      setMessage('Configuration Gemini enregistrée. Les prochains matchings utiliseront ces valeurs sans redémarrage.');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const clearStoredKey = async (): Promise<void> => {
    setSaving(true);
    setError('');
    setMessage('');

    try {
      const response = await api<AiSettings>('/settings/ai', {
        method: 'PUT',
        body: JSON.stringify({ clearApiKey: true }),
      });
      setSettings(response);
      setApiKey('');
      setMessage(
        response.apiKeySource === 'environment'
          ? 'Clé enregistrée dans JobPilot supprimée. La clé .env est de nouveau utilisée.'
          : 'Clé Gemini enregistrée supprimée.',
      );
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  if (settings === null && error === '') return <Loading />;

  return (
    <>
      <PageHeader
        title="Configuration & clés API"
        description="Gère les fournisseurs externes et leurs secrets depuis l’interface locale, sans exposer les clés après enregistrement."
      />

      {message !== '' && <div className="notice">{message}</div>}
      {error !== '' && <ErrorBox message={error} />}
      <div style={{ height: 14 }} />

      {settings !== null && (
        <div className="grid cols-2">
          <Card>
            <div className="actions" style={{ justifyContent: 'space-between' }}>
              <div>
                <h2 className="section-title" style={{ marginBottom: 6 }}>Gemini — matching IA</h2>
                <p className="muted" style={{ margin: 0 }}>Provider actif pour le premier test IA de JobPilot.</p>
              </div>
              <Badge tone={settings.apiKeyConfigured ? 'good' : 'warn'}>
                {settings.apiKeyConfigured ? 'Clé configurée' : 'Clé requise'}
              </Badge>
            </div>

            <div className="stack" style={{ marginTop: 18 }}>
              <label className="checkbox-label">
                <input
                  type="checkbox"
                  checked={enabled}
                  onChange={(event) => setEnabled(event.target.checked)}
                />
                Activer le matching IA
              </label>

              <label>
                Modèle
                <input
                  value={model}
                  onChange={(event) => setModel(event.target.value)}
                  placeholder="gemini-3.5-flash-lite"
                  autoComplete="off"
                />
              </label>

              <label>
                Clé API Gemini
                <input
                  type="password"
                  value={apiKey}
                  onChange={(event) => setApiKey(event.target.value)}
                  placeholder={settings.apiKeyConfigured ? '••••••••••••••••' : 'Saisir une clé API'}
                  autoComplete="new-password"
                />
              </label>

              <div className="small muted">
                {sourceLabel(settings.apiKeySource)}. Une valeur vide conserve la clé actuelle.
                La clé n’est jamais renvoyée par l’API ni réaffichée dans cette page.
              </div>

              <div className="actions">
                <button className="btn" type="button" disabled={saving || model.trim() === ''} onClick={() => void save()}>
                  {saving ? 'Enregistrement…' : 'Enregistrer'}
                </button>
                {settings.apiKeySource === 'interface' && (
                  <button className="btn secondary" type="button" disabled={saving} onClick={() => void clearStoredKey()}>
                    Supprimer la clé enregistrée
                  </button>
                )}
              </div>
            </div>
          </Card>

          <Card>
            <h2 className="section-title">Sécurité et mode test</h2>
            <div className="stack">
              <div className="notice">
                Les clés enregistrées depuis l’interface sont chiffrées dans le volume privé local de JobPilot avec <code>APP_ENCRYPTION_KEY</code>.
              </div>
              <div className="notice warning">
                <strong>Gemini free tier :</strong> pour cette phase de test, JobPilot continue d’envoyer uniquement les critères de matching minimisés et les données de l’offre. Le CV complet, le nom, les e-mails et Gmail ne sont pas envoyés.
              </div>
              <div className="small muted">
                Ordre de priorité : configuration enregistrée dans l’interface, puis variables <code>.env</code>. En cas d’erreur ou de quota Gemini, le matcher déterministe reste le fallback.
              </div>
            </div>
          </Card>

          <Card>
            <h2 className="section-title">Autres fournisseurs</h2>
            <p className="muted">
              Cette page constitue le coffre de configuration initial. Les prochaines intégrations pourront ajouter OpenAI, Mistral, Anthropic, France Travail, Adzuna et les autres connecteurs sans exposer leurs secrets dans l’interface.
            </p>
            <div className="actions">
              <Badge>OpenAI — à venir</Badge>
              <Badge>Mistral — à venir</Badge>
              <Badge>Anthropic — à venir</Badge>
              <Badge>Connecteurs — à venir</Badge>
            </div>
          </Card>
        </div>
      )}
    </>
  );
}
