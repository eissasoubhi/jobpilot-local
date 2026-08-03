'use client';

import { useCallback, useEffect, useState } from 'react';

import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import type { Application } from '@/lib/types';

function companyName(application: Application): string {
  return application.jobOffer.company || application.jobOffer.clientName || 'Entreprise non renseignée';
}

export default function ApplicationsPage() {
  const [items, setItems] = useState<Application[] | null>(null);
  const [selected, setSelected] = useState<Application | null>(null);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [saving, setSaving] = useState(false);

  const load = useCallback(async (): Promise<void> => {
    try {
      setItems(await api<Application[]>('/applications'));
      setError('');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const save = async (
    statusOverride?: string,
    successMessage = 'Modifications enregistrées.',
  ): Promise<void> => {
    if (selected === null) return;

    setSaving(true);

    try {
      const updated = await api<Application>(`/applications/${selected.id}`, {
        method: 'PATCH',
        body: JSON.stringify({
          status: statusOverride ?? selected.status,
          message: selected.message,
          coverLetter: selected.coverLetter,
          compensationAnswer: selected.compensationAnswer,
          confirmationRef: selected.confirmationRef,
        }),
      });
      setSelected(updated);
      setNotice(successMessage);
      setError('');
      await load();
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const confirmSubmission = async (): Promise<void> => {
    if (selected === null || selected.status === 'SUBMITTED') return;

    const confirmed = window.confirm(
      'Confirme uniquement après avoir réellement envoyé la candidature sur la plateforme d’origine. JobPilot va enregistrer le suivi, mais il n’envoie pas la candidature à ta place.',
    );

    if (!confirmed) return;

    await save(
      'SUBMITTED',
      'Candidature marquée comme envoyée. La date d’envoi a été enregistrée dans JobPilot.',
    );
  };

  const copyText = async (label: string, value: string): Promise<void> => {
    try {
      await navigator.clipboard.writeText(value);
      setNotice(`${label} copié dans le presse-papiers.`);
      setError('');
    } catch {
      setError(`Impossible de copier ${label.toLowerCase()}.`);
    }
  };

  const openApplication = (application: Application): void => {
    setSelected(application);
    setNotice('');
    setError('');
  };

  return (
    <>
      <PageHeader
        title="Candidatures"
        description="Vérifie, adapte et envoie chaque candidature depuis la plateforme d’origine, puis enregistre son suivi dans JobPilot."
      />
      {error !== '' && <ErrorBox message={error} />}

      <Card>
        {items === null ? (
          <Loading />
        ) : items.length === 0 ? (
          <Empty>Aucune candidature préparée.</Empty>
        ) : (
          items.map((application) => (
            <div className="list-row" key={application.id}>
              <div style={{ flex: 1 }}>
                <h3>{application.jobOffer.title}</h3>
                <div className="muted small">
                  {companyName(application)} · {application.jobOffer.location || 'Lieu non renseigné'} ·{' '}
                  {application.jobOffer.contractType || 'Contrat non renseigné'}
                </div>
                <div className="actions" style={{ marginTop: 8 }}>
                  <Badge tone={application.status === 'SUBMITTED' ? 'good' : 'blue'}>
                    {application.status === 'SUBMITTED' ? 'ENVOYÉE' : application.status}
                  </Badge>
                  <Badge tone="blue">Score {application.jobOffer.score}</Badge>
                  <Badge>{application.jobOffer.language.toUpperCase()}</Badge>
                  {application.cvDocument && <Badge>{application.cvDocument.name}</Badge>}
                  {application.compensationAnswer && (
                    <Badge tone="good">{application.compensationAnswer}</Badge>
                  )}
                </div>
              </div>
              <button
                className="btn secondary small"
                type="button"
                onClick={() => openApplication(application)}
              >
                {application.status === 'SUBMITTED' ? 'Voir le suivi' : 'Examiner et postuler'}
              </button>
            </div>
          ))
        )}
      </Card>

      {selected && (
        <div className="modal-backdrop" onMouseDown={() => setSelected(null)}>
          <div
            className="modal"
            role="dialog"
            aria-modal="true"
            aria-label={`Candidature ${selected.jobOffer.title}`}
            onMouseDown={(event) => event.stopPropagation()}
          >
            <PageHeader
              title={selected.status === 'SUBMITTED' ? 'Suivi de la candidature' : 'Candidature préparée'}
              description="JobPilot prépare les éléments et suit l’avancement. L’envoi réel se fait sur la plateforme de l’annonce."
              actions={
                <button className="btn secondary" type="button" onClick={() => setSelected(null)}>
                  Fermer
                </button>
              }
            />

            {notice !== '' && <div className="notice" style={{ marginBottom: 14 }}>{notice}</div>}
            {error !== '' && <ErrorBox message={error} />}

            <div className="notice warning" style={{ marginBottom: 14 }}>
              <strong>JobPilot n’envoie pas automatiquement la candidature.</strong>{' '}
              Il prépare le CV, le message et la lettre. Tu dois ouvrir le site d’origine, compléter ou coller les informations, puis valider l’envoi sur ce site.
            </div>

            <section className="card" aria-labelledby="application-job-title">
              <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div>
                  <div className="small muted" style={{ fontWeight: 700, textTransform: 'uppercase', letterSpacing: '.04em' }}>
                    Offre concernée
                  </div>
                  <h2 id="application-job-title" style={{ margin: '7px 0 5px', fontSize: 21 }}>
                    {selected.jobOffer.title}
                  </h2>
                  <div>
                    <strong>{companyName(selected)}</strong>
                    {selected.jobOffer.clientName && selected.jobOffer.clientName !== selected.jobOffer.company && (
                      <span className="muted"> · Client final : {selected.jobOffer.clientName}</span>
                    )}
                  </div>
                </div>
                <div className="score" aria-label={`Score ${selected.jobOffer.score}`}>
                  {selected.jobOffer.score}
                </div>
              </div>

              <div className="actions" style={{ marginTop: 13 }}>
                <Badge>{selected.jobOffer.location || 'Lieu non renseigné'}</Badge>
                <Badge>{selected.jobOffer.contractType || 'Contrat non renseigné'}</Badge>
                <Badge>{selected.jobOffer.workMode || 'Mode de travail non renseigné'}</Badge>
                <Badge tone="blue">Source : {selected.jobOffer.source || 'inconnue'}</Badge>
                <Badge>{selected.jobOffer.language.toUpperCase()}</Badge>
              </div>

              <div className="actions" style={{ marginTop: 14 }}>
                {selected.cvDocument && (
                  <a
                    className="btn secondary small"
                    href={selected.cvDocument.downloadUrl}
                    target="_blank"
                    rel="noreferrer"
                  >
                    Ouvrir le CV sélectionné
                  </a>
                )}
              </div>

              <details style={{ marginTop: 15 }}>
                <summary className="small" style={{ cursor: 'pointer', fontWeight: 700 }}>
                  Afficher la description complète de l’offre
                </summary>
                <div className="small" style={{ whiteSpace: 'pre-wrap', lineHeight: 1.6, marginTop: 12 }}>
                  {selected.jobOffer.description || 'Description non disponible.'}
                </div>
              </details>
            </section>

            <div className="stack" style={{ marginTop: 16 }}>
              <div>
                <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'center', marginBottom: 7 }}>
                  <strong className="small">Message</strong>
                  <button
                    className="btn secondary small"
                    type="button"
                    onClick={() => void copyText('Message', selected.message)}
                  >
                    Copier le message
                  </button>
                </div>
                <textarea
                  aria-label="Message"
                  value={selected.message}
                  onChange={(event) => setSelected({ ...selected, message: event.target.value })}
                />
              </div>

              <div>
                <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'center', marginBottom: 7 }}>
                  <strong className="small">Lettre de motivation</strong>
                  <button
                    className="btn secondary small"
                    type="button"
                    onClick={() => void copyText('Lettre de motivation', selected.coverLetter)}
                  >
                    Copier la lettre
                  </button>
                </div>
                <textarea
                  aria-label="Lettre de motivation"
                  style={{ minHeight: 200 }}
                  value={selected.coverLetter}
                  onChange={(event) => setSelected({ ...selected, coverLetter: event.target.value })}
                />
              </div>

              <label>
                Réponse rémunération
                <input
                  value={selected.compensationAnswer ?? ''}
                  onChange={(event) =>
                    setSelected({ ...selected, compensationAnswer: event.target.value })
                  }
                />
              </label>

              <label>
                Confirmation / référence obtenue après l’envoi
                <input
                  value={selected.confirmationRef ?? ''}
                  onChange={(event) =>
                    setSelected({ ...selected, confirmationRef: event.target.value })
                  }
                />
              </label>

              <label>
                Statut de suivi dans JobPilot
                <select
                  value={selected.status}
                  onChange={(event) => setSelected({ ...selected, status: event.target.value })}
                >
                  <option value="READY_TO_SUBMIT">Prête à envoyer</option>
                  <option value="SUBMITTED">Envoyée</option>
                  <option value="RECRUITER_REPLIED">Réponse recruteur</option>
                  <option value="INTERVIEW">Entretien</option>
                  <option value="REJECTED">Refusée</option>
                  <option value="OFFER_RECEIVED">Offre reçue</option>
                </select>
                <span className="small muted">Ce statut sert uniquement au suivi et ne déclenche aucun envoi.</span>
              </label>
            </div>

            <section className="card" aria-labelledby="submission-steps-title" style={{ marginTop: 16 }}>
              <h2 id="submission-steps-title" className="section-title">Finaliser la candidature</h2>
              <div className="stack">
                <div>
                  <strong>1. Enregistre tes modifications</strong>
                  <div className="small muted">Le message, la lettre, la rémunération et la référence restent sauvegardés localement.</div>
                </div>
                <button
                  className="btn secondary"
                  type="button"
                  disabled={saving}
                  onClick={() => void save(undefined, 'Modifications enregistrées. Tu peux maintenant postuler sur la plateforme d’origine.')}
                >
                  {saving ? 'Enregistrement…' : 'Étape 1 — Enregistrer mes modifications'}
                </button>

                <div>
                  <strong>2. Envoie la candidature sur le site de l’annonce</strong>
                  <div className="small muted">Une nouvelle fenêtre s’ouvre. JobPilot ne remplit ni ne valide automatiquement le formulaire externe.</div>
                </div>
                {selected.jobOffer.sourceUrl ? (
                  <a
                    className="btn"
                    href={selected.jobOffer.sourceUrl}
                    target="_blank"
                    rel="noreferrer"
                    onClick={() => setNotice('Plateforme ouverte dans un nouvel onglet. Reviens ici après avoir réellement validé l’envoi.')}
                  >
                    Étape 2 — Ouvrir la plateforme pour postuler
                  </a>
                ) : (
                  <div className="notice warning">
                    Aucun lien vers l’annonce originale n’est disponible. Recherche l’offre manuellement avec son titre et son entreprise, puis utilise les éléments préparés ci-dessus.
                  </div>
                )}

                <div>
                  <strong>3. Confirme le suivi après l’envoi réel</strong>
                  <div className="small muted">Cette action ne soumet rien : elle marque simplement la candidature comme envoyée et enregistre la date.</div>
                </div>
                <button
                  className="btn"
                  type="button"
                  disabled={saving || selected.status === 'SUBMITTED'}
                  onClick={() => void confirmSubmission()}
                >
                  {selected.status === 'SUBMITTED'
                    ? 'Candidature déjà marquée comme envoyée'
                    : saving
                      ? 'Enregistrement…'
                      : 'Étape 3 — J’ai envoyé la candidature'}
                </button>
              </div>
            </section>
          </div>
        </div>
      )}
    </>
  );
}
