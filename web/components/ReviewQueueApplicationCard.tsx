'use client';

import { useEffect, useState } from 'react';

import { Badge } from '@/components/UI';
import { api } from '@/lib/api';
import { applicationBadgeLabel, applicationStatusTone } from '@/lib/application-status';
import { getErrorMessage } from '@/lib/errors';
import type { Application } from '@/lib/types';

type ReviewQueueApplicationCardProps = {
  application: Application;
  onApplicationUpdated?: (application: Application) => void;
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
  onApplicationUpdated,
}: ReviewQueueApplicationCardProps) {
  const [currentApplication, setCurrentApplication] = useState(application);
  const [selectedStatus, setSelectedStatus] = useState(application.status);
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    setCurrentApplication(application);
    setSelectedStatus(application.status);
    setNotice('');
    setError('');
  }, [application]);

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
      setCurrentApplication(updated);
      setSelectedStatus(updated.status);
      onApplicationUpdated?.(updated);
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

  const job = currentApplication.jobOffer;
  const contractLabel = job.contractType?.trim() || 'Non renseigné';
  const isCdi = /(^|\W)cdi($|\W)/i.test(contractLabel);
  const hasCoverLetter = currentApplication.coverLetter.trim() !== '';
  const hasCompensation = (currentApplication.compensationAnswer ?? '').trim() !== '';
  const scoreReasons = job.scoreReasons ?? [];

  return (
    <article className="review-queue-card" aria-label={`Offre à examiner : ${job.title}`}>
      <header className="review-queue-card-header">
        <div className="review-queue-card-title-block">
          <div className="review-queue-eyebrow">Prête à envoyer</div>
          <h2>{job.title}</h2>
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
