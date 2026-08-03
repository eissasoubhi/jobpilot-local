'use client';

import { useEffect, useState } from 'react';

import { Badge, Card, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Application, Settings } from '@/lib/types';

type Source = { name: string; url: string; category: string; mode: string };
type GmailStatus = {
  connected: boolean;
  sendPermission: boolean;
  sendPermissionMessage?: string | null;
  configured: boolean;
  missingVariables: string[];
  redirectUri: string;
  startUrl: string;
};
type EmailPreview = {
  applicationId: number;
  subject: string;
  body: string;
  attachmentNames: string[];
};
type TestSendResult = EmailPreview & {
  sent: boolean;
  recipient: string;
  gmailMessageId: string;
  applicationStatusChanged: boolean;
  dailyLimitConsumed: boolean;
};

function applicationLabel(application: Application): string {
  const company = application.jobOffer.company || application.jobOffer.clientName || 'Entreprise non renseignée';

  return `${application.jobOffer.title} — ${company} — score ${application.jobOffer.score}`;
}

function isValidEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
}

export default function SettingsPage() {
  const [settings, setSettings] = useState<Settings | null>(null);
  const [sources, setSources] = useState<Source[]>([]);
  const [applications, setApplications] = useState<Application[]>([]);
  const [gmailStatus, setGmailStatus] = useState<GmailStatus | null>(null);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [testRecipient, setTestRecipient] = useState('');
  const [testApplicationId, setTestApplicationId] = useState('');
  const [emailPreview, setEmailPreview] = useState<EmailPreview | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [testRefreshing, setTestRefreshing] = useState(false);
  const [testSending, setTestSending] = useState(false);
  const [testError, setTestError] = useState('');
  const [testMessage, setTestMessage] = useState('');

  useEffect(() => {
    const parameters = new URLSearchParams(window.location.search);
    const gmailResult = parameters.get('gmail');
    const gmailError = parameters.get('gmail_error');

    if (gmailResult === 'connected') {
      setMessage('Gmail est maintenant connecté. Les permissions accordées sont affichées ci-dessous.');
    }

    if (gmailError) {
      setError(`Connexion Gmail impossible : ${gmailError}`);
    }

    if (gmailResult || gmailError) {
      window.history.replaceState({}, '', window.location.pathname);
    }
  }, []);

  useEffect(() => {
    let active = true;

    void Promise.all([
      api<Settings>('/settings'),
      api<Source[]>('/settings/sources'),
      api<GmailStatus>('/integrations/gmail/status'),
    ])
      .then(([loadedSettings, loadedSources, loadedGmailStatus]) => {
        if (!active) return;
        setSettings(loadedSettings);
        setSources(loadedSources);
        setGmailStatus(loadedGmailStatus);
      })
      .catch((caughtError: unknown) => {
        if (active) setError(getErrorMessage(caughtError));
      });

    void api<Application[]>('/applications')
      .then((loadedApplications) => {
        if (!active) return;
        setApplications(loadedApplications);
        setTestApplicationId((current) => {
          if (current !== '') return current;
          const firstTestable = loadedApplications.find((application) => application.cvDocument != null);
          return firstTestable ? String(firstTestable.id) : '';
        });
      })
      .catch((caughtError: unknown) => {
        if (active) setTestError(`Chargement des candidatures impossible : ${getErrorMessage(caughtError)}`);
      });

    return () => {
      active = false;
    };
  }, []);

  useEffect(() => {
    if (testApplicationId === '') {
      setEmailPreview(null);
      return;
    }

    let active = true;
    setPreviewLoading(true);
    setTestError('');
    setTestMessage('');

    void api<EmailPreview>(`/integrations/gmail/test-preview/${testApplicationId}`)
      .then((preview) => {
        if (active) setEmailPreview(preview);
      })
      .catch((caughtError: unknown) => {
        if (!active) return;
        setEmailPreview(null);
        setTestError(getErrorMessage(caughtError));
      })
      .finally(() => {
        if (active) setPreviewLoading(false);
      });

    return () => {
      active = false;
    };
  }, [testApplicationId]);

  if (settings === null || gmailStatus === null) {
    return error !== '' ? <ErrorBox message={error} /> : <Loading />;
  }

  const testableApplications = applications.filter((application) => application.cvDocument != null);
  const testRecipientValid = isValidEmail(testRecipient);
  const testBlocker = (() => {
    if (!gmailStatus.connected) return 'Connecte Gmail avant de lancer un test.';
    if (!gmailStatus.sendPermission) {
      return gmailStatus.sendPermissionMessage ?? 'Reconnecte Gmail avec le droit gmail.send.';
    }
    if (testableApplications.length === 0) return 'Prépare une candidature avec un CV sélectionné.';
    if (testApplicationId === '') return 'Sélectionne une candidature.';
    if (previewLoading) return 'L’aperçu du mail est en cours de préparation.';
    if (emailPreview === null) return 'L’aperçu du mail n’est pas disponible.';
    if (!testRecipientValid) return 'Saisis une adresse e-mail de destination valide.';
    return null;
  })();

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
      setGmailStatus({
        ...gmailStatus,
        connected: false,
        sendPermission: false,
        sendPermissionMessage: 'Gmail n’est pas connecté.',
      });
      setMessage('Gmail a été déconnecté.');
      setError('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  };

  const refreshTestData = async (): Promise<void> => {
    setTestRefreshing(true);
    setTestError('');
    setTestMessage('');

    try {
      const [loadedGmailStatus, loadedApplications] = await Promise.all([
        api<GmailStatus>('/integrations/gmail/status'),
        api<Application[]>('/applications'),
      ]);
      setGmailStatus(loadedGmailStatus);
      setApplications(loadedApplications);

      const currentStillExists = loadedApplications.some(
        (application) => String(application.id) === testApplicationId && application.cvDocument != null,
      );
      if (!currentStillExists) {
        const firstTestable = loadedApplications.find((application) => application.cvDocument != null);
        setTestApplicationId(firstTestable ? String(firstTestable.id) : '');
        setEmailPreview(null);
      }

      setTestMessage('Diagnostic Gmail et candidatures actualisés.');
    } catch (caughtError: unknown) {
      setTestError(getErrorMessage(caughtError));
    } finally {
      setTestRefreshing(false);
    }
  };

  const sendTestEmail = async (): Promise<void> => {
    if (testBlocker !== null || emailPreview === null) {
      setTestError(testBlocker ?? 'Le test n’est pas prêt.');
      return;
    }

    const confirmed = window.confirm(
      `Envoyer maintenant ce mail réel à ${testRecipient.trim()} avec le sujet « ${emailPreview.subject} » ? Le statut de la candidature ne sera pas modifié.`,
    );
    if (!confirmed) return;

    setTestSending(true);
    setTestError('');
    setTestMessage('');

    try {
      const result = await api<TestSendResult>('/integrations/gmail/test-send', {
        method: 'POST',
        body: JSON.stringify({
          recipient: testRecipient.trim(),
          applicationId: emailPreview.applicationId,
        }),
      });
      setTestMessage(
        `Mail de test envoyé à ${result.recipient}. Identifiant Gmail : ${result.gmailMessageId}. La candidature et la limite quotidienne n’ont pas été modifiées.`,
      );
    } catch (caughtError: unknown) {
      setTestError(getErrorMessage(caughtError));
    } finally {
      setTestSending(false);
    }
  };

  return (
    <>
      <PageHeader
        title="Paramètres"
        description="Règles de recherche, score, rémunération, envoi et intégrations."
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
            <label>TJM Île-de-France<input type="number" value={settings.defaultIdfTjm} onChange={(event) => set('defaultIdfTjm', Number(event.target.value))} /></label>
            <label>TJM hors Île-de-France<input type="number" value={settings.defaultOutsideIdfTjm} onChange={(event) => set('defaultOutsideIdfTjm', Number(event.target.value))} /></label>
            <label>TJM remote sans lieu connu<input type="number" value={settings.defaultRemoteTjm} onChange={(event) => set('defaultRemoteTjm', Number(event.target.value))} /></label>
            <label>TJM minimum<input type="number" value={settings.minimumFreelanceTjm} onChange={(event) => set('minimumFreelanceTjm', Number(event.target.value))} /></label>
            <label>TJM maximum<input type="number" value={settings.maximumTjm} onChange={(event) => set('maximumTjm', Number(event.target.value))} /></label>
            <label>Salaire CDI minimum<input type="number" value={settings.minimumCdiSalary} onChange={(event) => set('minimumCdiSalary', Number(event.target.value))} /></label>
          </div>
          <div className="notice" style={{ marginTop: 14 }}>
            Fourchette ≤ 500 € : maximum. Au-dessus de 500 € : milieu de la fourchette, plafonné au TJM maximum. Le full remote suit la règle géographique lorsque le lieu est connu.
          </div>
        </Card>

        <Card>
          <h2 className="section-title">Gmail</h2>
          <p className="muted">Connexion OAuth. Aucun mot de passe n’est stocké.</p>

          {gmailStatus.connected ? (
            <div className="stack">
              <div className="actions">
                <Badge tone="good">Connecté</Badge>
                <Badge tone={gmailStatus.sendPermission ? 'good' : 'warn'}>
                  {gmailStatus.sendPermission ? 'Lecture + envoi autorisés' : 'Lecture seule'}
                </Badge>
                <button className="btn secondary" type="button" onClick={() => void disconnectGmail()}>
                  Déconnecter
                </button>
              </div>
              {gmailStatus.sendPermission ? (
                <div className="notice">
                  JobPilot peut lire les réponses et envoyer uniquement les candidatures éligibles par e-mail lorsque l’automatisation est activée.
                </div>
              ) : (
                <div className="notice warning">
                  <strong>L’autorisation d’envoi manque.</strong>{' '}
                  {gmailStatus.sendPermissionMessage ?? 'Reconnecte Gmail pour accepter le nouveau droit d’envoi.'}
                  <div style={{ marginTop: 10 }}>
                    <a className="btn" href={gmailStatus.startUrl}>Reconnecter avec l’autorisation d’envoi</a>
                  </div>
                </div>
              )}
            </div>
          ) : gmailStatus.configured ? (
            <div className="stack">
              <div className="actions">
                <Badge tone="blue">Prêt à connecter</Badge>
                <a className="btn" href={gmailStatus.startUrl}>
                  Connecter Gmail
                </a>
              </div>
              <div className="notice">
                Google demandera les permissions de lecture et d’envoi. L’envoi automatique restera désactivé tant que tu ne l’actives pas ci-dessous.
              </div>
            </div>
          ) : (
            <div className="stack">
              <div className="actions"><Badge tone="warn">Configuration Google requise</Badge></div>
              <div className="notice warning">
                <strong>Le bouton Gmail est désactivé car la configuration OAuth est incomplète.</strong>
                <div style={{ marginTop: 8 }}>Variables manquantes dans <code>.env</code> : <code>{gmailStatus.missingVariables.join(', ')}</code></div>
                <div style={{ marginTop: 8 }}>URI de redirection à déclarer exactement dans Google Cloud : <code>{gmailStatus.redirectUri}</code></div>
                <div style={{ marginTop: 8 }}>Après avoir renseigné les variables, recrée le conteneur API pour activer le bouton.</div>
              </div>
              <button className="btn" type="button" disabled title="Configuration Google incomplète">Connecter Gmail</button>
            </div>
          )}
        </Card>

        <Card>
          <h2 className="section-title">Tester l’envoi automatique</h2>
          <p className="muted">
            Envoie à l’adresse de ton choix une copie exacte du mail automatique d’une candidature préparée.
          </p>
          <div className="stack">
            {testMessage !== '' && <div className="notice">{testMessage}</div>}
            {testError !== '' && <ErrorBox message={testError} />}

            <div className="actions">
              <button
                className="btn secondary small"
                type="button"
                disabled={testRefreshing || testSending}
                onClick={() => void refreshTestData()}
              >
                {testRefreshing ? 'Actualisation…' : 'Actualiser le diagnostic'}
              </button>
            </div>

            <label>
              Adresse e-mail de destination
              <input
                type="email"
                placeholder="mon-adresse-de-test@example.com"
                value={testRecipient}
                aria-invalid={testRecipient !== '' && !testRecipientValid}
                onChange={(event) => {
                  setTestRecipient(event.target.value);
                  setTestError('');
                  setTestMessage('');
                }}
              />
            </label>

            <label>
              Candidature à reproduire
              <select value={testApplicationId} onChange={(event) => setTestApplicationId(event.target.value)}>
                <option value="">Sélectionner une candidature</option>
                {testableApplications.map((application) => (
                  <option key={application.id} value={application.id}>
                    {applicationLabel(application)}
                  </option>
                ))}
              </select>
            </label>

            {testableApplications.length === 0 && (
              <div className="notice warning">
                Prépare d’abord une candidature avec un CV sélectionné. Elle apparaîtra ensuite dans cette liste.
              </div>
            )}

            {previewLoading && <div className="muted small">Préparation de l’aperçu…</div>}

            {emailPreview && (
              <div className="stack">
                <label>
                  Sujet exact
                  <input readOnly value={emailPreview.subject} />
                </label>
                <label>
                  Corps exact reçu par le destinataire
                  <textarea readOnly style={{ minHeight: 240 }} value={emailPreview.body} />
                </label>
                <div className="notice">
                  <strong>Pièce jointe :</strong>{' '}
                  {emailPreview.attachmentNames.length > 0
                    ? emailPreview.attachmentNames.join(', ')
                    : 'aucune'}
                </div>
              </div>
            )}

            {testBlocker !== null && !testSending && (
              <div className="notice warning" data-testid="email-test-blocker">
                <strong>Test non prêt :</strong> {testBlocker}
              </div>
            )}

            <button
              className="btn"
              type="button"
              disabled={testSending || testBlocker !== null}
              onClick={() => void sendTestEmail()}
            >
              {testSending ? 'Envoi du test…' : 'Envoyer le mail de test'}
            </button>

            <div className="notice warning">
              <strong>Ce test envoie un vrai e-mail.</strong> Il ne change pas le statut de la candidature, ne renseigne pas sa date d’envoi et ne consomme pas la limite quotidienne.
            </div>
          </div>
        </Card>

        <Card>
          <h2 className="section-title">Finalisation des candidatures</h2>
          <div className="stack">
            <label>
              Parcours de finalisation
              <select value={settings.finalSubmissionMode} onChange={(event) => set('finalSubmissionMode', event.target.value)}>
                <option value="ONE_CLICK">Guidé : ouvrir la plateforme puis confirmer dans JobPilot</option>
                <option value="PREPARE_ONLY">Préparer uniquement, sans suivi de l’envoi</option>
                <option value="AUTOMATIC_AUTHORIZED_ONLY">Automatique uniquement par canal officiel autorisé</option>
              </select>
            </label>

            <label className="checkbox-label">
              <input
                type="checkbox"
                checked={settings.autoSubmitEnabled}
                onChange={(event) => setSettings({
                  ...settings,
                  autoSubmitEnabled: event.target.checked,
                  finalSubmissionMode: event.target.checked ? 'AUTOMATIC_AUTHORIZED_ONLY' : settings.finalSubmissionMode,
                })}
              />
              Envoyer automatiquement les candidatures éligibles par Gmail
            </label>

            <div className="form-grid">
              <label>
                Score minimum pour l’envoi
                <input
                  type="number"
                  min="1"
                  max="100"
                  value={settings.autoSubmitThreshold}
                  onChange={(event) => set('autoSubmitThreshold', Number(event.target.value))}
                />
              </label>
              <label>
                Limite quotidienne d’envois
                <input
                  type="number"
                  min="1"
                  max="50"
                  value={settings.autoSubmitDailyLimit}
                  onChange={(event) => set('autoSubmitDailyLimit', Number(event.target.value))}
                />
              </label>
            </div>

            <div className="notice warning">
              <strong>Conditions obligatoires :</strong> score au moins égal au seuil, offre non exclue, adresse e-mail de candidature identifiable, CV sélectionné, Gmail connecté avec le droit d’envoi et aucune soumission précédente. Les formulaires LinkedIn, Indeed et sites carrière ne sont jamais contournés ni validés automatiquement.
            </div>

            {settings.autoSubmitEnabled && !gmailStatus.sendPermission && (
              <div className="notice warning">
                L’automatisation est configurée, mais aucun e-mail ne partira tant que Gmail n’aura pas l’autorisation d’envoi.
              </div>
            )}
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
                  <td><a className="btn secondary small" href={source.url} target="_blank" rel="noreferrer">Ouvrir</a></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </>
  );
}
