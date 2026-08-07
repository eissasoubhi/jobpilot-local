'use client';

import { useCallback, useEffect, useState } from 'react';

import { ApplicationStatusFilter } from '@/components/ApplicationStatusFilter';
import { CoverLetterEditor } from '@/components/CoverLetterEditor';
import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import {
  applicationBadgeLabel,
  applicationStatusLabel,
  applicationStatusTone,
  filterApplications,
  type ApplicationStatusFilter as ApplicationStatusFilterValue,
} from '@/lib/application-status';
import { getErrorMessage } from '@/lib/errors';
import type { Application } from '@/lib/types';

function companyName(application: Application): string {
  return application.jobOffer.company || application.jobOffer.clientName || 'Entreprise non renseignée';
}

export default function ApplicationsPage() {
  const [items, setItems] = useState<Application[] | null>(null);
  const [selected, setSelected] = useState<Application | null>(null);
  const [statusFilter, setStatusFilter] = useState<ApplicationStatusFilterValue>('ALL');
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

  const markSubmitted = async (): Promise<void> => {
    if (selected === null || selected.status === 'SUBMITTED') return;

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

  const filteredItems = items === null ? null : filterApplications(items, statusFilter);

  return (
    <>
      <PageHeader
        title="Candidatures"
        description="Suis et filtre les candidatures préparées, envoyées manuellement ou transmises automatiquement par un canal officiel autorisé."
      />
      {error !== '' && <ErrorBox message={error} />}

      <Card>
        {items === null || filteredItems === null ? (
          <Loading />
        ) : items.length === 0 ? (
          <Empty>Aucune candidature préparée.</Empty>
        ) : (
          <>
            <ApplicationStatusFilter
              applications={items}
              value={statusFilter}
              onChange={setStatusFilter}
            />

            {filteredItems.length === 0 ? (
              <Empty>
                Aucune candidature dans le statut « {applicationStatusLabel(statusFilter)} ».
              </Empty>
            ) : filteredItems.map((application) => (
              <div className="list-row" key={application.id}>
                <div style={{ flex: 1 }}>
                  <h3>{application.jobOffer.title}</h3>
                  <div className="muted small">
                    {companyName(application)} · {application.jobOffer.location || 'Lieu non renseigné'} ·{' '}
                    {application.jobOffer.contractType || 'Contrat non renseigné'}
                  </div>
                  <div className="actions" style={{ marginTop: 8 }}>
                    <Badge tone={applicationStatusTone(application.status)}>{applicationBadgeLabel(application)}</Badge>
                    <Badge tone="blue">Score {application.jobOffer.score}</Badge>
                    <Badge>{application.jobOffer.language.toUpperCase()}</Badge>
                    {application.cvDocument && <Badge>{application.cvDocument.name}</Badge>}
                    {application.jobOffer.applicationEmail && <Badge>{application.jobOffer.applicationEmail}</Badge>}
                    {application.compensationAnswer && <Badge tone="good">{application.compensationAnswer}</Badge>}
                  </div>
                  {application.submissionError && (
                    <div className="small" style={{ marginTop: 8 }}>
                      <strong>Erreur :</strong> {application.submissionError}
                    </div>
                  )}
                </div>
                <button className="btn secondary small" type="button" onClick={() => openApplication(application)}>
                  {application.status === 'SUBMITTED' ? 'Voir le suivi' : 'Examiner et postuler'}
                </button>
              </div>
            ))}
          </>
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
              description="JobPilot prépare les éléments, utilise Gmail uniquement lorsque les conditions d’envoi automatique sont réunies, et conserve le suivi."
              actions={<button className="btn secondary" type="button" onClick={() => setSelected(null)}>Fermer</button>}
            />

            {notice !== '' && <div className="notice" style={{ marginBottom: 14 }}>{notice}</div>}
            {error !== '' && <ErrorBox message={error} />}

            {selected.status === 'SUBMITTED' && selected.channel === 'Gmail automatique' ? (
              <div className="notice" style={{ marginBottom: 14 }}>
                <strong>Candidature envoyée automatiquement par Gmail.</strong>{' '}
                {selected.gmailMessageId && <>Identifiant Gmail : <code>{selected.gmailMessageId}</code>.</>}
              </div>
            ) : selected.status === 'SUBMISSION_PENDING' ? (
              <div className="notice warning" style={{ marginBottom: 14 }}>
                <strong>Envoi Gmail en cours.</strong> JobPilot bloque toute nouvelle tentative pour éviter un doublon pendant cette étape.
              </div>
            ) : selected.status === 'SUBMISSION_FAILED' ? (
              <div className="notice warning" style={{ marginBottom: 14 }}>
                <strong>L’envoi automatique a échoué.</strong>{' '}
                {selected.submissionError || 'Consulte les logs de l’API, puis utilise le parcours manuel ci-dessous.'}
              </div>
            ) : (
              <div className="notice warning" style={{ marginBottom: 14 }}>
                <strong>JobPilot n’envoie pas automatiquement la candidature.</strong>{' '}
                Il prépare le CV et le message, ainsi qu’une lettre uniquement lorsque l’offre la demande. Tu dois ouvrir le site d’origine, compléter ou coller les informations, puis valider l’envoi sur ce site lorsqu’aucun e-mail officiel utilisable n’est disponible.
              </div>
            )}

            <section className="card" aria-labelledby="application-job-title">
              <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div>
                  <div className="small muted" style={{ fontWeight: 700, textTransform: 'uppercase', letterSpacing: '.04em' }}>Offre concernée</div>
                  <h2 id="application-job-title" style={{ margin: '7px 0 5px', fontSize: 21 }}>{selected.jobOffer.title}</h2>
                  <div>
                    <strong>{companyName(selected)}</strong>
                    {selected.jobOffer.clientName && selected.jobOffer.clientName !== selected.jobOffer.company && (
                      <span className="muted"> · Client final : {selected.jobOffer.clientName}</span>
                    )}
                  </div>
                </div>
                <div className="score" aria-label={`Score ${selected.jobOffer.score}`}>{selected.jobOffer.score}</div>
              </div>

              <div className="actions" style={{ marginTop: 13 }}>
                <Badge>{selected.jobOffer.location || 'Lieu non renseigné'}</Badge>
                <Badge>{selected.jobOffer.contractType || 'Contrat non renseigné'}</Badge>
                <Badge>{selected.jobOffer.workMode || 'Mode de travail non renseigné'}</Badge>
                <Badge tone="blue">Source : {selected.jobOffer.source || 'inconnue'}</Badge>
                <Badge>{selected.jobOffer.language.toUpperCase()}</Badge>
                {selected.jobOffer.applicationEmail && <Badge tone="good">Destinataire : {selected.jobOffer.applicationEmail}</Badge>}
              </div>

              <div className="actions" style={{ marginTop: 14 }}>
                {selected.cvDocument && (
                  <a className="btn secondary small" href={selected.cvDocument.downloadUrl} target="_blank" rel="noreferrer">
                    Ouvrir le CV sélectionné
                  </a>
                )}
              </div>

              <details style={{ marginTop: 15 }}>
                <summary className="small" style={{ cursor: 'pointer', fontWeight: 700 }}>Afficher la description complète de l’offre</summary>
                <div className="small" style={{ whiteSpace: 'pre-wrap', lineHeight: 1.6, marginTop: 12 }}>
                  {selected.jobOffer.description || 'Description non disponible.'}
                </div>
              </details>
            </section>

            <div className="stack" style={{ marginTop: 16 }}>
              <div>
                <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'center', marginBottom: 7 }}>
                  <strong className="small">Message</strong>
                  <button className="btn secondary small" type="button" onClick={() => void copyText('Message', selected.message)}>Copier le message</button>
                </div>
                <textarea aria-label="Message" value={selected.message} onChange={(event) => setSelected({ ...selected, message: event.target.value })} />
              </div>

              <CoverLetterEditor
                key={selected.id}
                value={selected.coverLetter}
                onChange={(coverLetter) => setSelected({ ...selected, coverLetter })}
                onCopy={(coverLetter) => copyText('Lettre de motivation', coverLetter)}
              />

              <label>
                Réponse rémunération
                <input value={selected.compensationAnswer ?? ''} onChange={(event) => setSelected({ ...selected, compensationAnswer: event.target.value })} />
              </label>

              <label>
                Confirmation / référence obtenue après l’envoi
                <input value={selected.confirmationRef ?? ''} onChange={(event) => setSelected({ ...selected, confirmationRef: event.target.value })} />
              </label>

              <label>
                Statut de suivi dans JobPilot
                <select value={selected.status} onChange={(event) => setSelected({ ...selected, status: event.target.value })} disabled={selected.status === 'SUBMISSION_PENDING'}>
                  <option value="READY_TO_SUBMIT">Prête à envoyer</option>
                  <option value="SUBMISSION_FAILED">Échec de l’envoi automatique</option>
                  <option value="SUBMITTED">Envoyée</option>
                  <option value="RECRUITER_REPLIED">Réponse recruteur</option>
                  <option value="INTERVIEW">Entretien</option>
                  <option value="REJECTED">Refusée</option>
                  <option value="OFFER_RECEIVED">Offre reçue</option>
                </select>
                <span className="small muted">Une modification manuelle du statut sert au suivi et ne déclenche aucun envoi externe.</span>
              </label>
            </div>

            {selected.status !== 'SUBMISSION_PENDING' && !(selected.status === 'SUBMITTED' && selected.channel === 'Gmail automatique') && (
              <section className="card" aria-labelledby="submission-steps-title" style={{ marginTop: 16 }}>
                <h2 id="submission-steps-title" className="section-title">Finaliser la candidature</h2>
                <div className="stack">
                  <div>
                    <strong>1. Enregistre tes modifications</strong>
                    <div className="small muted">Le message, la lettre, la rémunération et la référence restent sauvegardés localement.</div>
                  </div>
                  <button className="btn secondary" type="button" disabled={saving} onClick={() => void save(undefined, 'Modifications enregistrées. Tu peux maintenant postuler sur la plateforme d’origine.')}>
                    {saving ? 'Enregistrement…' : 'Étape 1 — Enregistrer mes modifications'}
                  </button>

                  <div>
                    <strong>2. Envoie la candidature sur le site de l’annonce</strong>
                    <div className="small muted">Une nouvelle fenêtre s’ouvre. JobPilot ne remplit ni ne valide automatiquement le formulaire externe.</div>
                  </div>
                  {selected.jobOffer.sourceUrl ? (
                    <a className="btn" href={selected.jobOffer.sourceUrl} target="_blank" rel="noreferrer" onClick={() => setNotice('Plateforme ouverte dans un nouvel onglet. Reviens ici après avoir réellement validé l’envoi.')}>
                      Étape 2 — Ouvrir la plateforme pour postuler
                    </a>
                  ) : (
                    <div className="notice warning">Aucun lien vers l’annonce originale n’est disponible. Recherche l’offre manuellement avec son titre et son entreprise, puis utilise les éléments préparés ci-dessus.</div>
                  )}

                  <div>
                    <strong>3. Marque la candidature comme envoyée</strong>
                    <div className="small muted">Après l’envoi réel sur la plateforme, ce bouton met immédiatement à jour le suivi dans JobPilot sans ouvrir de confirmation.</div>
                  </div>
                  <button className="btn" type="button" disabled={saving || selected.status === 'SUBMITTED'} onClick={() => void markSubmitted()}>
                    {selected.status === 'SUBMITTED' ? 'Candidature déjà marquée comme envoyée' : saving ? 'Enregistrement…' : 'Étape 3 — J’ai envoyé la candidature'}
                  </button>
                </div>
              </section>
            )}
          </div>
        </div>
      )}
    </>
  );
}
