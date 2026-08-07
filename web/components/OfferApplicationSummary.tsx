'use client';

import { useEffect, useState } from 'react';

import { Badge } from '@/components/UI';
import { api } from '@/lib/api';
import { applicationBadgeLabel, applicationStatusTone } from '@/lib/application-status';
import { getErrorMessage } from '@/lib/errors';
import type { Application } from '@/lib/types';

type OfferApplicationSummaryProps = {
  application: Application;
  onApplicationUpdated?: (application: Application) => void;
};

export function OfferApplicationSummary({
  application,
  onApplicationUpdated,
}: OfferApplicationSummaryProps) {
  const [currentApplication, setCurrentApplication] = useState(application);
  const [reviewOpen, setReviewOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');
  const hasMessage = currentApplication.message.trim() !== '';
  const hasCoverLetter = currentApplication.coverLetter.trim() !== '';
  const hasCompensation = (currentApplication.compensationAnswer ?? '').trim() !== '';

  useEffect(() => {
    setCurrentApplication(application);
  }, [application]);

  useEffect(() => {
    if (!reviewOpen) return undefined;

    const closeOnEscape = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') setReviewOpen(false);
    };

    window.addEventListener('keydown', closeOnEscape);
    return () => window.removeEventListener('keydown', closeOnEscape);
  }, [reviewOpen]);

  const saveApplication = async (
    status: string,
    successMessage: string,
  ): Promise<void> => {
    if (saving) return;

    setSaving(true);
    setNotice('');
    setError('');

    try {
      const updated = await api<Application>(`/applications/${currentApplication.id}`, {
        method: 'PATCH',
        body: JSON.stringify({
          status,
          message: currentApplication.message,
          coverLetter: currentApplication.coverLetter,
          compensationAnswer: currentApplication.compensationAnswer,
          confirmationRef: currentApplication.confirmationRef,
        }),
      });
      setCurrentApplication(updated);
      onApplicationUpdated?.(updated);
      setNotice(successMessage);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const savePreparation = async (): Promise<void> => {
    await saveApplication(currentApplication.status, 'Modifications enregistrées dans JobPilot.');
  };

  const saveTrackingStatus = async (): Promise<void> => {
    await saveApplication(currentApplication.status, 'Statut de suivi enregistré dans JobPilot.');
  };

  const markSubmitted = async (): Promise<void> => {
    if (currentApplication.status === 'SUBMITTED') return;

    await saveApplication(
      'SUBMITTED',
      'Candidature marquée comme envoyée. La date d’envoi a été enregistrée dans JobPilot.',
    );
  };

  const markIgnoredNotMatch = async (): Promise<void> => {
    if (currentApplication.status === 'IGNORED_NOT_MATCH') return;

    await saveApplication(
      'IGNORED_NOT_MATCH',
      'Offre marquée comme ne correspondant pas au profil.',
    );
  };

  return (
    <section
      className="notice"
      aria-label={`Candidature préparée pour ${currentApplication.jobOffer.title}`}
      style={{ marginTop: 12 }}
    >
      <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'center' }}>
        <strong>Candidature</strong>
        <Badge tone={applicationStatusTone(currentApplication.status)}>
          {applicationBadgeLabel(currentApplication)}
        </Badge>
      </div>

      <div className="actions" style={{ marginTop: 8 }}>
        {currentApplication.cvDocument && <Badge tone="good">CV prêt</Badge>}
        {hasMessage && <Badge tone="good">Message prêt</Badge>}
        {hasCoverLetter && <Badge tone="good">Lettre prête</Badge>}
        {hasCompensation && <Badge tone="good">Rémunération prête</Badge>}
      </div>

      <div className="actions" style={{ marginTop: 10 }}>
        <button className="btn secondary small" type="button" onClick={() => setReviewOpen(true)}>
          Examiner
        </button>
        {currentApplication.jobOffer.sourceUrl && (
          <a
            className="btn small"
            href={currentApplication.jobOffer.sourceUrl}
            target="_blank"
            rel="noreferrer"
          >
            Ouvrir la plateforme pour postuler
          </a>
        )}
      </div>

      <details style={{ marginTop: 10 }}>
        <summary className="small" style={{ cursor: 'pointer', fontWeight: 700 }}>
          Aperçu rapide des éléments préparés
        </summary>

        <div className="stack" style={{ gap: 12, marginTop: 12 }}>
          {currentApplication.cvDocument && (
            <div>
              <div className="small muted">CV sélectionné</div>
              <div className="actions" style={{ marginTop: 5 }}>
                <strong className="small">{currentApplication.cvDocument.name}</strong>
                <a
                  className="btn secondary small"
                  href={currentApplication.cvDocument.downloadUrl}
                  target="_blank"
                  rel="noreferrer"
                >
                  Ouvrir le CV
                </a>
              </div>
            </div>
          )}

          {hasMessage && (
            <div>
              <div className="small muted">Message préparé</div>
              <div className="small" style={{ whiteSpace: 'pre-wrap', lineHeight: 1.55, marginTop: 5 }}>
                {currentApplication.message}
              </div>
            </div>
          )}

          {hasCoverLetter && (
            <div>
              <div className="small muted">Lettre de motivation demandée</div>
              <div className="small" style={{ whiteSpace: 'pre-wrap', lineHeight: 1.55, marginTop: 5 }}>
                {currentApplication.coverLetter}
              </div>
            </div>
          )}

          {hasCompensation && (
            <div>
              <div className="small muted">Réponse rémunération</div>
              <strong className="small">{currentApplication.compensationAnswer}</strong>
            </div>
          )}
        </div>
      </details>

      {reviewOpen && (
        <>
          <button
            type="button"
            aria-label="Fermer l’examen de l’offre"
            onClick={() => setReviewOpen(false)}
            style={{
              position: 'fixed',
              inset: 0,
              zIndex: 40,
              border: 0,
              background: 'rgba(15, 23, 42, 0.38)',
              cursor: 'default',
            }}
          />
          <aside
            role="dialog"
            aria-modal="true"
            aria-labelledby={`offer-review-title-${currentApplication.id}`}
            style={{
              position: 'fixed',
              top: 0,
              right: 0,
              bottom: 0,
              zIndex: 50,
              width: 'min(680px, 94vw)',
              overflowY: 'auto',
              background: 'var(--surface, #fff)',
              boxShadow: '-16px 0 40px rgba(15, 23, 42, 0.18)',
              padding: 24,
            }}
          >
            <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
              <div>
                <div className="small muted">Examen de l’offre</div>
                <h2 id={`offer-review-title-${currentApplication.id}`} style={{ marginTop: 4 }}>
                  {currentApplication.jobOffer.title}
                </h2>
                <div className="small muted">
                  {currentApplication.jobOffer.company || 'Entreprise non renseignée'} · {currentApplication.jobOffer.location || 'Lieu non renseigné'} · {currentApplication.jobOffer.workMode || 'Mode non renseigné'}
                </div>
              </div>
              <button className="btn secondary small" type="button" onClick={() => setReviewOpen(false)}>
                Fermer
              </button>
            </div>

            <div className="stack" style={{ gap: 18, marginTop: 22 }}>
              <section>
                <div className="actions">
                  <Badge tone="blue">Score : {currentApplication.jobOffer.score} %</Badge>
                  <Badge>{currentApplication.jobOffer.contractType || 'Contrat inconnu'}</Badge>
                  <Badge tone={applicationStatusTone(currentApplication.status)}>{applicationBadgeLabel(currentApplication)}</Badge>
                </div>
              </section>

              <section>
                <strong>Description</strong>
                <div className="small" style={{ whiteSpace: 'pre-wrap', lineHeight: 1.6, marginTop: 7 }}>
                  {currentApplication.jobOffer.description || 'Description non disponible.'}
                </div>
              </section>

              <section>
                <strong>Pourquoi ce score ?</strong>
                {currentApplication.jobOffer.scoreReasons.length > 0 ? (
                  <ul style={{ marginBottom: 0 }}>
                    {currentApplication.jobOffer.scoreReasons.map((reason) => <li className="small" key={reason}>{reason}</li>)}
                  </ul>
                ) : (
                  <div className="small muted" style={{ marginTop: 7 }}>Aucune explication détaillée disponible.</div>
                )}
              </section>

              {currentApplication.cvDocument && (
                <section>
                  <strong>CV sélectionné</strong>
                  <div className="actions" style={{ marginTop: 7 }}>
                    <span className="small">{currentApplication.cvDocument.name}</span>
                    <a className="btn secondary small" href={currentApplication.cvDocument.downloadUrl} target="_blank" rel="noreferrer">
                      Ouvrir le CV
                    </a>
                  </div>
                </section>
              )}

              <section>
                <strong>Éléments de candidature</strong>
                <div className="small muted" style={{ marginTop: 7 }}>
                  Tu peux ajuster les éléments préparés ici avant d’ouvrir la plateforme d’origine. L’enregistrement reste local à JobPilot et ne déclenche aucun envoi externe.
                </div>
                <div className="stack" style={{ gap: 12, marginTop: 12 }}>
                  <label>
                    Message préparé
                    <textarea
                      aria-label="Message préparé"
                      value={currentApplication.message}
                      onChange={(event) => setCurrentApplication({ ...currentApplication, message: event.target.value })}
                    />
                  </label>

                  <label>
                    Lettre de motivation demandée
                    <textarea
                      aria-label="Lettre de motivation demandée"
                      value={currentApplication.coverLetter}
                      onChange={(event) => setCurrentApplication({ ...currentApplication, coverLetter: event.target.value })}
                    />
                  </label>

                  <label>
                    Réponse rémunération
                    <input
                      aria-label="Réponse rémunération"
                      value={currentApplication.compensationAnswer ?? ''}
                      onChange={(event) => setCurrentApplication({ ...currentApplication, compensationAnswer: event.target.value })}
                    />
                  </label>

                  <label>
                    Confirmation / référence après envoi
                    <input
                      aria-label="Confirmation / référence après envoi"
                      value={currentApplication.confirmationRef ?? ''}
                      onChange={(event) => setCurrentApplication({ ...currentApplication, confirmationRef: event.target.value })}
                    />
                  </label>

                  <div className="actions">
                    <button className="btn secondary small" type="button" disabled={saving} onClick={() => void savePreparation()}>
                      {saving ? 'Enregistrement…' : 'Enregistrer les modifications'}
                    </button>
                  </div>
                </div>
              </section>

              <section>
                <strong>Décision</strong>
                <div className="small muted" style={{ marginTop: 7 }}>
                  Si l’offre ne correspond pas à ton profil, marque-la ici. Elle quittera la boîte À traiter sans être supprimée ni envoyer quoi que ce soit à la plateforme.
                </div>
                <div className="actions" style={{ marginTop: 10 }}>
                  <button
                    className="btn secondary small"
                    type="button"
                    disabled={saving || currentApplication.status === 'IGNORED_NOT_MATCH'}
                    onClick={() => void markIgnoredNotMatch()}
                  >
                    {currentApplication.status === 'IGNORED_NOT_MATCH'
                      ? 'Déjà marquée comme non correspondante'
                      : saving ? 'Enregistrement…' : 'Ne correspond pas à mon profil'}
                  </button>
                </div>
              </section>

              <section>
                <strong>Suivi</strong>
                <div className="small muted" style={{ marginTop: 7 }}>
                  Mets à jour ici l’état réel de la candidature. Ces changements servent uniquement au suivi dans JobPilot et ne déclenchent aucun envoi externe.
                </div>
                <div className="stack" style={{ gap: 10, marginTop: 10 }}>
                  <label>
                    Statut de suivi dans JobPilot
                    <select
                      aria-label="Statut de suivi dans JobPilot"
                      value={currentApplication.status}
                      disabled={saving || currentApplication.status === 'SUBMISSION_PENDING'}
                      onChange={(event) => setCurrentApplication({ ...currentApplication, status: event.target.value })}
                    >
                      <option value="READY_TO_SUBMIT">Prête à envoyer</option>
                      <option value="SUBMISSION_FAILED">Échec de l’envoi automatique</option>
                      <option value="SUBMITTED">Envoyée</option>
                      <option value="RECRUITER_REPLIED">Réponse recruteur</option>
                      <option value="INTERVIEW">Entretien</option>
                      <option value="REJECTED">Refusée</option>
                      <option value="OFFER_RECEIVED">Offre reçue</option>
                      <option value="IGNORED_NOT_MATCH">Ne correspond pas au profil</option>
                    </select>
                  </label>
                  <div className="actions">
                    <button
                      className="btn secondary small"
                      type="button"
                      disabled={saving || currentApplication.status === 'SUBMISSION_PENDING'}
                      onClick={() => void saveTrackingStatus()}
                    >
                      {saving ? 'Enregistrement…' : 'Enregistrer le statut'}
                    </button>
                    <button
                      className="btn secondary small"
                      type="button"
                      disabled={saving || currentApplication.status === 'SUBMITTED'}
                      onClick={() => void markSubmitted()}
                    >
                      {currentApplication.status === 'SUBMITTED'
                        ? 'Candidature déjà marquée comme envoyée'
                        : saving ? 'Enregistrement…' : 'J’ai envoyé la candidature'}
                    </button>
                  </div>
                </div>
                {notice !== '' && <div className="small" role="status" style={{ marginTop: 8 }}>{notice}</div>}
                {error !== '' && <div className="small" role="alert" style={{ marginTop: 8 }}>{error}</div>}
              </section>

              {currentApplication.jobOffer.sourceUrl && (
                <section className="actions">
                  <a className="btn" href={currentApplication.jobOffer.sourceUrl} target="_blank" rel="noreferrer">
                    Ouvrir la plateforme pour postuler
                  </a>
                </section>
              )}
            </div>
          </aside>
        </>
      )}
    </section>
  );
}
