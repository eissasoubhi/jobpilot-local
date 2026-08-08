'use client';

import { useEffect, useState } from 'react';

import { Badge, Card, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';

type ConfigurationSource = 'interface' | 'environment' | 'none';

type AiQuota = {
  rpm: number;
  tpm: number;
  rpd: number;
  safetyPercent: number;
};

type AiQuotaUsage = {
  rpmUsed: number;
  tpmUsed: number;
  rpdUsed: number;
  rpmLimit: number;
  tpmLimit: number;
  rpdLimit: number;
  providerRpm: number;
  providerTpm: number;
  providerRpd: number;
  safetyPercent: number;
  resetsAt: string;
  resetTimeZone: string;
};

type AiSettings = {
  provider: 'gemini';
  enabled: boolean;
  model: string;
  apiKeyConfigured: boolean;
  apiKeySource: ConfigurationSource;
  hasInterfaceOverrides: boolean;
  quota: AiQuota;
  quotaUsage: AiQuotaUsage;
};

type IntegrationField = {
  label: string;
  secret: boolean;
  configured: boolean;
  source: ConfigurationSource;
  value: string | null;
};

type ExternalIntegration = {
  id: string;
  label: string;
  category: 'ai' | 'connector';
  runtimeActive: boolean;
  note: string;
  fields: Record<string, IntegrationField>;
};

function sourceLabel(source: ConfigurationSource): string {
  if (source === 'interface') return 'Enregistré dans JobPilot';
  if (source === 'environment') return 'Fourni par .env';
  return 'Non configuré';
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat('fr-FR').format(value);
}

function ExternalIntegrationCard({
  integration,
  onUpdated,
}: {
  integration: ExternalIntegration;
  onUpdated: (configuration: ExternalIntegration) => void;
}) {
  const [values, setValues] = useState<Record<string, string>>({});
  const [secrets, setSecrets] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  useEffect(() => {
    const nextValues: Record<string, string> = {};
    for (const [field, configuration] of Object.entries(integration.fields)) {
      if (!configuration.secret) nextValues[field] = configuration.value ?? '';
    }
    setValues(nextValues);
    setSecrets({});
  }, [integration]);

  const save = async (): Promise<void> => {
    setSaving(true);
    setError('');
    setMessage('');

    try {
      const secretPayload: Record<string, string> = {};
      for (const [field, value] of Object.entries(secrets)) {
        if (value.trim() !== '') secretPayload[field] = value.trim();
      }

      const response = await api<ExternalIntegration>(`/settings/integrations/${integration.id}`, {
        method: 'PUT',
        body: JSON.stringify({ values, secrets: secretPayload }),
      });
      onUpdated(response);
      setSecrets({});
      setMessage(`${integration.label} enregistré.`);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const clearSecret = async (field: string): Promise<void> => {
    setSaving(true);
    setError('');
    setMessage('');

    try {
      const response = await api<ExternalIntegration>(`/settings/integrations/${integration.id}`, {
        method: 'PUT',
        body: JSON.stringify({ clearSecrets: [field] }),
      });
      onUpdated(response);
      setSecrets((current) => ({ ...current, [field]: '' }));
      setMessage(
        response.fields[field]?.source === 'environment'
          ? `${integration.fields[field].label} supprimé de JobPilot. La valeur .env est de nouveau utilisée.`
          : `${integration.fields[field].label} supprimé de JobPilot.`,
      );
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const fullyConfigured = Object.values(integration.fields).every((field) => field.configured);

  return (
    <Card>
      <div className="actions" style={{ justifyContent: 'space-between' }}>
        <div>
          <h2 className="section-title" style={{ marginBottom: 6 }}>{integration.label}</h2>
          <p className="muted" style={{ margin: 0 }}>{integration.note}</p>
        </div>
        <div className="actions">
          <Badge tone={integration.runtimeActive ? 'blue' : 'neutral'}>
            {integration.runtimeActive ? 'Utilisé par JobPilot' : 'Prêt pour future bascule'}
          </Badge>
          <Badge tone={fullyConfigured ? 'good' : 'warn'}>
            {fullyConfigured ? 'Configuré' : 'À compléter'}
          </Badge>
        </div>
      </div>

      <div className="stack" style={{ marginTop: 18 }}>
        {Object.entries(integration.fields).map(([field, configuration]) => (
          <div className="stack" key={field} style={{ gap: 6 }}>
            <label>
              {configuration.label}
              <input
                aria-label={`${integration.label} — ${configuration.label}`}
                type={configuration.secret ? 'password' : 'text'}
                value={configuration.secret ? (secrets[field] ?? '') : (values[field] ?? '')}
                onChange={(event) => {
                  if (configuration.secret) {
                    setSecrets((current) => ({ ...current, [field]: event.target.value }));
                  } else {
                    setValues((current) => ({ ...current, [field]: event.target.value }));
                  }
                }}
                placeholder={configuration.secret && configuration.configured ? '••••••••••••••••' : ''}
                autoComplete={configuration.secret ? 'new-password' : 'off'}
              />
            </label>
            <div className="small muted">
              {sourceLabel(configuration.source)}.
              {configuration.secret && ' Une valeur vide conserve le secret actuel ; sa valeur n’est jamais renvoyée au navigateur.'}
            </div>
            {configuration.secret && configuration.source === 'interface' && (
              <div>
                <button
                  className="btn secondary small"
                  type="button"
                  disabled={saving}
                  onClick={() => void clearSecret(field)}
                >
                  Supprimer {configuration.label.toLocaleLowerCase('fr')}
                </button>
              </div>
            )}
          </div>
        ))}

        {error !== '' && <ErrorBox message={error} />}
        {message !== '' && <div className="notice">{message}</div>}

        <div className="actions">
          <button className="btn" type="button" disabled={saving} onClick={() => void save()}>
            {saving ? 'Enregistrement…' : `Enregistrer ${integration.label}`}
          </button>
        </div>
      </div>
    </Card>
  );
}

export default function IntegrationSettingsPage() {
  const [settings, setSettings] = useState<AiSettings | null>(null);
  const [integrations, setIntegrations] = useState<ExternalIntegration[] | null>(null);
  const [enabled, setEnabled] = useState(false);
  const [model, setModel] = useState('gemini-3.5-flash-lite');
  const [apiKey, setApiKey] = useState('');
  const [quotaRpm, setQuotaRpm] = useState(15);
  const [quotaTpm, setQuotaTpm] = useState(250000);
  const [quotaRpd, setQuotaRpd] = useState(500);
  const [quotaSafetyPercent, setQuotaSafetyPercent] = useState(80);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const applyAiSettings = (aiSettings: AiSettings): void => {
    setSettings(aiSettings);
    setEnabled(aiSettings.enabled);
    setModel(aiSettings.model);
    setQuotaRpm(aiSettings.quota.rpm);
    setQuotaTpm(aiSettings.quota.tpm);
    setQuotaRpd(aiSettings.quota.rpd);
    setQuotaSafetyPercent(aiSettings.quota.safetyPercent);
  };

  useEffect(() => {
    let active = true;

    void Promise.all([
      api<AiSettings>('/settings/ai'),
      api<ExternalIntegration[]>('/settings/integrations'),
    ])
      .then(([aiSettings, externalIntegrations]) => {
        if (!active) return;
        applyAiSettings(aiSettings);
        setIntegrations(externalIntegrations);
      })
      .catch((caughtError: unknown) => {
        if (active) setError(getErrorMessage(caughtError));
      });

    return () => {
      active = false;
    };
  }, []);

  const updateIntegration = (configuration: ExternalIntegration): void => {
    setIntegrations((current) => current?.map((item) => item.id === configuration.id ? configuration : item) ?? [configuration]);
  };

  const saveGemini = async (): Promise<void> => {
    setSaving(true);
    setError('');
    setMessage('');

    try {
      const payload: Record<string, unknown> = {
        enabled,
        model,
        quotaRpm,
        quotaTpm,
        quotaRpd,
        quotaSafetyPercent,
      };
      if (apiKey.trim() !== '') payload.apiKey = apiKey.trim();

      const response = await api<AiSettings>('/settings/ai', {
        method: 'PUT',
        body: JSON.stringify(payload),
      });
      applyAiSettings(response);
      setApiKey('');
      setMessage('Configuration Gemini et quotas enregistrés. Les prochains matchings respecteront ces limites sans redémarrage.');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const clearGeminiKey = async (): Promise<void> => {
    setSaving(true);
    setError('');
    setMessage('');

    try {
      const response = await api<AiSettings>('/settings/ai', {
        method: 'PUT',
        body: JSON.stringify({ clearApiKey: true }),
      });
      applyAiSettings(response);
      setApiKey('');
      setMessage(
        response.apiKeySource === 'environment'
          ? 'Clé Gemini enregistrée dans JobPilot supprimée. La clé .env est de nouveau utilisée.'
          : 'Clé Gemini enregistrée supprimée.',
      );
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  if ((settings === null || integrations === null) && error === '') return <Loading />;

  const aiProviders = integrations?.filter((integration) => integration.category === 'ai') ?? [];
  const connectors = integrations?.filter((integration) => integration.category === 'connector') ?? [];
  const quotaValid = quotaRpm > 0 && quotaTpm > 0 && quotaRpd > 0 && quotaSafetyPercent > 0 && quotaSafetyPercent <= 100;

  return (
    <>
      <PageHeader
        title="Configuration & clés API"
        description="Gère les fournisseurs IA et connecteurs externes depuis un coffre local chiffré, avec contrôle des quotas par modèle."
      />

      {message !== '' && <div className="notice">{message}</div>}
      {error !== '' && <ErrorBox message={error} />}
      <div style={{ height: 14 }} />

      {settings !== null && (
        <div className="grid cols-2">
          <Card>
            <div className="actions" style={{ justifyContent: 'space-between' }}>
              <div>
                <h2 className="section-title" style={{ marginBottom: 6 }}>Gemini — matching IA actif</h2>
                <p className="muted" style={{ margin: 0 }}>Provider utilisé actuellement pour le test de matching sémantique.</p>
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

              <h3 style={{ marginBottom: 0 }}>Quotas du modèle</h3>
              <div className="grid cols-2">
                <label>
                  Requêtes / minute (RPM)
                  <input
                    aria-label="Quota Gemini RPM"
                    type="number"
                    min={1}
                    value={quotaRpm}
                    onChange={(event) => setQuotaRpm(Number(event.target.value))}
                  />
                </label>
                <label>
                  Tokens d’entrée / minute (TPM)
                  <input
                    aria-label="Quota Gemini TPM"
                    type="number"
                    min={1}
                    value={quotaTpm}
                    onChange={(event) => setQuotaTpm(Number(event.target.value))}
                  />
                </label>
                <label>
                  Requêtes / jour (RPD)
                  <input
                    aria-label="Quota Gemini RPD"
                    type="number"
                    min={1}
                    value={quotaRpd}
                    onChange={(event) => setQuotaRpd(Number(event.target.value))}
                  />
                </label>
                <label>
                  Pourcentage utilisable
                  <input
                    aria-label="Marge quota Gemini"
                    type="number"
                    min={1}
                    max={100}
                    value={quotaSafetyPercent}
                    onChange={(event) => setQuotaSafetyPercent(Number(event.target.value))}
                  />
                </label>
              </div>

              <div className="notice">
                <strong>Protection locale actuelle :</strong>{' '}
                {settings.quotaUsage.rpmUsed}/{settings.quotaUsage.rpmLimit} requêtes/minute ·{' '}
                {formatNumber(settings.quotaUsage.tpmUsed)}/{formatNumber(settings.quotaUsage.tpmLimit)} tokens/minute ·{' '}
                {settings.quotaUsage.rpdUsed}/{settings.quotaUsage.rpdLimit} requêtes/jour.
              </div>
              <div className="small muted">
                Plafonds fournisseur enregistrés : {settings.quotaUsage.providerRpm} RPM, {formatNumber(settings.quotaUsage.providerTpm)} TPM et {settings.quotaUsage.providerRpd} RPD.
                JobPilot n’en utilise que {settings.quotaUsage.safetyPercent} %. Le quota journalier Gemini est suivi selon {settings.quotaUsage.resetTimeZone}.
                Lors d’un changement de modèle ou de tier, recopie les limites affichées dans AI Studio.
              </div>

              <div className="actions">
                <button className="btn" type="button" disabled={saving || model.trim() === '' || !quotaValid} onClick={() => void saveGemini()}>
                  {saving ? 'Enregistrement…' : 'Enregistrer Gemini'}
                </button>
                {settings.apiKeySource === 'interface' && (
                  <button className="btn secondary" type="button" disabled={saving} onClick={() => void clearGeminiKey()}>
                    Supprimer la clé Gemini
                  </button>
                )}
              </div>
            </div>
          </Card>

          <Card>
            <h2 className="section-title">Sécurité, quotas et priorité</h2>
            <div className="stack">
              <div className="notice">
                Les secrets enregistrés ici sont chiffrés dans le volume privé local avec <code>APP_ENCRYPTION_KEY</code>. Ils ne sont pas stockés dans Doctrine ni dans Git.
              </div>
              <div className="notice warning">
                <strong>Gemini free tier :</strong> JobPilot continue d’envoyer uniquement les critères de matching minimisés et les données de l’offre. Le CV complet, le nom, les e-mails et Gmail ne sont pas envoyés pendant ce test.
              </div>
              <div className="notice warning">
                Le compteur local connaît uniquement les appels effectués par JobPilot. Une utilisation du même projet Gemini dans AI Studio ou une autre application n’est pas visible ici ; la marge configurable évite donc d’utiliser 100 % du quota annoncé.
              </div>
              <div className="small muted">
                Avant chaque appel, JobPilot réserve RPM, TPM et RPD pour le provider + modèle actif. Si une limite sûre est atteinte, aucun appel Gemini n’est envoyé et le matcher déterministe prend le relais. Après une réponse Gemini, le compteur TPM est corrigé avec <code>usage.total_input_tokens</code> lorsqu’il est disponible.
              </div>
              <div className="small muted">
                Priorité : valeur enregistrée dans l’interface, puis variable <code>.env</code>. Supprimer une valeur de l’interface réactive automatiquement le fallback <code>.env</code> lorsqu’il existe.
              </div>
            </div>
          </Card>
        </div>
      )}

      {aiProviders.length > 0 && (
        <>
          <div style={{ height: 22 }} />
          <h2>Fournisseurs IA alternatifs</h2>
          <p className="muted">Tu peux déjà enregistrer leurs clés et modèles. Le quota manager est provider/modèle-aware ; leurs limites seront branchées au moment où chaque provider de matching sera activé.</p>
          <div className="grid cols-2">
            {aiProviders.map((integration) => (
              <ExternalIntegrationCard key={integration.id} integration={integration} onUpdated={updateIntegration} />
            ))}
          </div>
        </>
      )}

      {connectors.length > 0 && (
        <>
          <div style={{ height: 22 }} />
          <h2>Connecteurs API</h2>
          <p className="muted">Les identifiants enregistrés ici prennent effet à chaud pour les connecteurs concernés. Les règles de quotas, politiques et conformité restent inchangées.</p>
          <div className="grid cols-2">
            {connectors.map((integration) => (
              <ExternalIntegrationCard key={integration.id} integration={integration} onUpdated={updateIntegration} />
            ))}
          </div>
        </>
      )}
    </>
  );
}
