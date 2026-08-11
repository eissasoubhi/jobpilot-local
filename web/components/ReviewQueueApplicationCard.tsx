'use client';

import type { Ref } from 'react';
import { useEffect, useState } from 'react';

import styles from '@/components/ReviewQueueApplicationCard.module.css';
import { ReviewQueueTechnologyComparison, type JobProfileComparison } from '@/components/ReviewQueueTechnologyComparison';
import { Badge } from '@/components/UI';
import { API_URL, api } from '@/lib/api';
import { applicationBadgeLabel, applicationStatusTone } from '@/lib/application-status';
import { getErrorMessage } from '@/lib/errors';
import type { Application } from '@/lib/types';

type ReviewQueueApplicationCardProps = {
  application: Application;
  headingRef?: Ref<HTMLHeadingElement>;
  onApplicationUpdated?: (application: Application) => void;
};

type EditableApplication = Application & {
  coverLetterManuallyEdited?: boolean;
  coverLetterEditedAt?: string | null;
};

const TRACKING_STATUSES = [
  ['READY_TO_SUBMIT', 'Prête à envoyer'],
  ['SUBMISSION_FAILED', 'Échec de l’envoi automatique'],
  ['SUBMITTED', 'Envoyée'],
  ['RECRUITER_REPLIED', 'Réponse recruteur'],
  ['INTERVIEW', 'Entretien'],
  ['REJECTED', 'Refusée'],
  ['OFFER_RECEIVED', 'Offre reçue'],
  ['IGNORED_NOT_MATCH', 'Ne correspond pas au profil'],
] as const;

export function ReviewQueueApplicationCard({
  application,
  headingRef,
  onApplicationUpdated,
}: ReviewQueueApplicationCardProps) {
  const [currentApplication, setCurrentApplication] = useState<Application>(application);
  const [selectedStatus, setSelectedStatus] = useState(application.status);
  const [saving, setSaving] = useState(false);
  const [coverLetterEditing, setCoverLetterEditing] = useState(false);
  const [coverLetterSaving, setCoverLetterSaving] = useState(false);
  const [coverLetterDraft, setCoverLetterDraft] = useState(application.coverLetter);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    setCurrentApplication(application);
    setSelectedStatus(application.status);
    setCoverLetterDraft(application.coverLetter);
    setCoverLetterEditing(false);
    setNotice('');
    setError('');
  }, [application]);

  const applyUpdatedApplication = (updated: Application): void => {
    setCurrentApplication(updated);
    setSelectedStatus(updated.status);
    setCoverLetterDraft(updated.coverLetter);
    onApplicationUpdated?.(updated);
  };

  const saveApplication = async (status: string, successMessage: string): Promise<void> => {
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
      applyUpdatedApplication(updated);
      setNotice(successMessage);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setSaving(false);
    }
  };

  const saveTrackingStatus = async (): Promise<void> => {
    if (selectedStatus === currentApplication.status) return;
    await saveApplication(selectedStatus, 'Statut de suivi enregistré dans JobPilot.');
  };

  const saveCoverLetter = async (): Promise<void> => {
    if (coverLetterSaving) return;

    setCoverLetterSaving(true);
    setNotice('');
    setError('');

    try {
      const updated = await api<EditableApplication>(`/applications/${currentApplication.id}/cover-letter`, {
        method: 'PATCH',
        body: JSON.stringify({ coverLetter: coverLetterDraft }),
      });
      applyUpdatedApplication(updated);
      setCoverLetterEditing(false);
      setNotice('Lettre de motivation enregistrée.');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setCoverLetterSaving(false);
    }
  };

  const resetCoverLetter = async (): Promise<void> => {
    if (coverLetterSaving) return;
    if (!window.confirm('Réinitialiser la lettre avec la dernière version générée par JobPilot ?')) return;

    setCoverLetterSaving(true);
    setNotice('');
    setError('');

    try {
      const updated = await api<EditableApplication>(`/applications/${currentApplication.id}/cover-letter/reset`, {
        method: 'POST',
      });
      applyUpdatedApplication(updated);
      setCoverLetterEditing(false);
      setNotice('Lettre réinitialisée depuis la dernière version générée.');
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setCoverLetterSaving(false);
    }
  };

  const copyCoverLetter = async (): Promise<void> => {
    setNotice('');
    setError('');

    try {
      await navigator.clipboard.writeText(currentApplication.coverLetter);
      setNotice('Lettre de motivation copiée.');
    } catch {
      setError('Impossible de copier la lettre de motivation dans le presse-papiers.');
    }
  };

  const startCoverLetterEditing = (): void => {
    setCoverLetterDraft(currentApplication.coverLetter);
    setCoverLetterEditing(true);
    setNotice('');
    setError('');
  };

  const cancelCoverLetterEditing = (): void => {
    setCoverLetterDraft(currentApplication.coverLetter);
    setCoverLetterEditing(false);
  };

  const job = currentApplication.jobOffer;
  const profileComparison = (currentApplication as Application & {
    profileComparison?: JobProfileComparison;
  }).profileComparison;
  const editableApplication = currentApplication as EditableApplication;
  const contractLabel = job.contractType?.trim() || 'Non renseigné';
  const isCdi = /(^|\W)cdi($|\W)/i.test(contractLabel);
  const hasCoverLetter = currentApplication.coverLetter.trim() !== '';
  const hasCompensation = (currentApplication.compensationAnswer ?? '').trim() !== '';
  const scoreReasons = job.scoreReasons ?? [];
  const coverLetterEditedAt = editableApplication.coverLetterEditedAt
    ? new Date(editableApplication.coverLetterEditedAt).toLocaleString('fr-FR')
    : null;

  return (
    <article className="review-queue-card" aria-label={`Offre à examiner : ${job.title}`}>
      <header className="review-queue-card-header">
        <div className="review-queue-card-title-block">
          <div className="review-queue-eyebrow">Prête à envoyer</div>
          <h2 ref={headingRef} tabIndex={-1}>{job.title}</h2>
          <div className="review-queue-card-meta">
            <span>{job.company || 'Entreprise non renseignée'}</span>
            <span>{job.location || 'Lieu non renseigné'}</span>
            <span>{job.workMode || 'Mode de travail non renseigné'}</span>
            <span>{job.source || 'Source non renseignée'}</span>
          </div>
        </div>
        <div className="review-queue-card-badges">
          <Badge tone={applicationStatusTone(currentApplication.status)}>
            {applicationBadgeLabel(currentApplication)}
          </Badge>
          <Badge tone={isCdi ? 'good' : 'neutral'}>{isCdi ? 'CDI' : 'Non-CDI'}</Badge>
          <Badge>Contrat : {contractLabel}</Badge>
        </div>
      </header>

      <div className="review-queue-readiness" aria-label="Éléments de candidature disponibles">
        {currentApplication.cvDocument ? (
          <span><strong>CV</strong> {currentApplication.cvDocument.name}</span>
        ) : (
          <span><strong>CV</strong> non sélectionné</span>
        )}
        <span><strong>Lettre</strong> {hasCoverLetter ? 'prête' : 'non préparée'}</span>
        <span><strong>Rémunération</strong> {hasCompensation ? currentApplication.compensationAnswer : 'non préparée'}</span>
      </div>

      <ReviewQueueTechnologyComparison comparison={profileComparison} />

      <section className="review-queue-actions-panel" aria-label="Actions secondaires sur la candidature">
        <div className="review-queue-primary-actions">
          {job.sourceUrl && (
            <a className="btn small" href={job.sourceUrl} target="_blank" rel="noreferrer">
              Ouvrir la plateforme
            </a>
          )}

          {currentApplication.cvDocument && (
            <a className="btn secondary small" href={currentApplication.cvDocument.downloadUrl} target="_blank" rel="noreferrer">
              Ouvrir le CV
            </a>
          )}
        </div>

        <div className="review-queue-status-inline">
          <label className="review-queue-status-control">
            Changer le statut
            <select
              aria-label="Statut de suivi dans JobPilot"
              value={selectedStatus}
              disabled={saving || currentApplication.status === 'SUBMISSION_PENDING'}
              onChange={(event) => setSelectedStatus(event.target.value)}
            >
              {TRACKING_STATUSES.map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </label>

          <button
            className="btn secondary small"
            type="button"
            disabled={saving
              || currentApplication.status === 'SUBMISSION_PENDING'
              || selectedStatus === currentApplication.status}
            onClick={() => void saveTrackingStatus()}
          >
            {saving ? 'Enregistrement…' : 'Appliquer'}
          </button>
        </div>
      </section>

      {notice !== '' && <div className="success-box review-queue-feedback" role="status">{notice}</div>}
      {error !== '' && <div className="error-box review-queue-feedback" role="alert">{error}</div>}

      <section className={styles.letterSection} aria-labelledby={`cover-letter-title-${currentApplication.id}`}>
        <div className={styles.letterHeader}>
          <div className={styles.letterTitleBlock}>
            <div className="review-queue-eyebrow">Candidature</div>
            <h3 id={`cover-letter-title-${currentApplication.id}`}>Lettre de motivation</h3>
            <div className={styles.letterMeta}>
              {editableApplication.coverLetterManuallyEdited ? (
                <>Modifiée manuellement{coverLetterEditedAt ? ` · ${coverLetterEditedAt}` : ''}</>
              ) : (
                <>Générée automatiquement par JobPilot</>
              )}
            </div>
          </div>

          {!coverLetterEditing && hasCoverLetter && (
            <div className={styles.letterActions}>
              <button className="btn secondary small" type="button" onClick={startCoverLetterEditing}>
                Modifier
              </button>
              <button className="btn secondary small" type="button" onClick={() => void copyCoverLetter()}>
                Copier
              </button>
              <a
                className="btn secondary small"
                href={`${API_URL}/applications/${currentApplication.id}/cover-letter/download`}
              >
                Télécharger
              </a>
              {editableApplication.coverLetterManuallyEdited && (
                <button
                  className="btn secondary small"
                  type="button"
                  disabled={coverLetterSaving}
                  onClick={() => void resetCoverLetter()}
                >
                  Réinitialiser
                </button>
              )}
            </div>
          )}
        </div>

        {!hasCoverLetter && !coverLetterEditing ? (
          <div className={styles.empty}>Aucune lettre de motivation n’est préparée pour cette candidature.</div>
        ) : coverLetterEditing ? (
          <div className={styles.editor}>
            <label htmlFor={`cover-letter-editor-${currentApplication.id}`}>Texte de la lettre</label>
            <textarea
              id={`cover-letter-editor-${currentApplication.id}`}
              value={coverLetterDraft}
              disabled={coverLetterSaving}
              onChange={(event) => setCoverLetterDraft(event.target.value)}
            />
            <div className={styles.editorFooter}>
              <div className={styles.editorHint}>Le téléchargement utilise toujours la dernière version enregistrée.</div>
              <div className={styles.editorButtons}>
                <button
                  className="btn secondary small"
                  type="button"
                  disabled={coverLetterSaving}
                  onClick={cancelCoverLetterEditing}
                >
                  Annuler
                </button>
                {editableApplication.coverLetterManuallyEdited && (
                  <button
                    className="btn secondary small"
                    type="button"
                    disabled={coverLetterSaving}
                    onClick={() => void resetCoverLetter()}
                  >
                    Réinitialiser
                  </button>
                )}
                <button
                  className="btn small"
                  type="button"
                  disabled={coverLetterSaving || coverLetterDraft.trim() === ''}
                  onClick={() => void saveCoverLetter()}
                >
                  {coverLetterSaving ? 'Enregistrement…' : 'Enregistrer'}
                </button>
              </div>
            </div>
          </div>
        ) : (
          <div className={styles.preview}>{currentApplication.coverLetter}</div>
        )}
      </section>

      <section className="review-queue-mission">
        <div className="review-queue-section-heading">
          <div>
            <div className="review-queue-eyebrow">Contexte de décision</div>
            <h3>Description de la mission</h3>
          </div>
        </div>
        <div className="review-queue-description">
          {job.description || 'Description non disponible.'}
        </div>
      </section>

      <section className="review-queue-score-panel">
        <div className="review-queue-score-summary">
          <div className="review-queue-score-value">{job.score}%</div>
          <div>
            <div className="review-queue-eyebrow">Matching JobPilot</div>
            <h3>Pourquoi ce score ?</h3>
          </div>
        </div>

        {scoreReasons.length > 0 ? (
          <ul className="review-queue-score-reasons">
            {scoreReasons.map((reason) => <li key={reason}>{reason}</li>)}
          </ul>
        ) : (
          <div className="muted">Aucune explication détaillée disponible.</div>
        )}
      </section>
    </article>
  );
}
