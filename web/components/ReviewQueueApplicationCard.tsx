'use client';

import type { Ref } from 'react';
import { useEffect, useState } from 'react';

import { CoverLetterDrawer } from '@/components/CoverLetterDrawer';
import styles from '@/components/ReviewQueueApplicationCard.module.css';
import { ReviewQueueTechnologyComparison, type JobProfileComparison } from '@/components/ReviewQueueTechnologyComparison';
import { Badge } from '@/components/UI';
import { api } from '@/lib/api';
import { applicationBadgeLabel, applicationStatusTone } from '@/lib/application-status';
import { getErrorMessage } from '@/lib/errors';
import { jobDescriptionToPlainText } from '@/lib/job-description';
import { offerPublicationTiming } from '@/lib/job-publication';
import type { Application } from '@/lib/types';

type ReviewQueueApplicationCardProps = {
  application: Application;
  headingRef?: Ref<HTMLHeadingElement>;
  onApplicationUpdated?: (application: Application) => void;
  onOfferUnavailableRequested?: () => void;
  decisionActionsDisabled?: boolean;
};

type EditableApplication = Application & {
  coverLetterManuallyEdited?: boolean;
};

type MotivationTab = 'coverLetter' | 'message';

const TRACKING_STATUSES = [
  ['READY_TO_SUBMIT', 'Prête à envoyer'],
  ['SUBMISSION_FAILED', 'Échec de l’envoi automatique'],
  ['SUBMITTED', 'Envoyée'],
  ['RECRUITER_REPLIED', 'Réponse recruteur'],
  ['INTERVIEW', 'Entretien'],
  ['REJECTED', 'Refusée'],
  ['OFFER_RECEIVED', 'Offre reçue'],
  ['OFFER_UNAVAILABLE', 'Offre indisponible'],
  ['IGNORED_NOT_MATCH', 'Ne correspond pas au profil'],
] as const;

function wordCount(value: string): number {
  const normalized = value.trim();
  return normalized === '' ? 0 : normalized.split(/\s+/).length;
}

export function ReviewQueueApplicationCard({
  application,
  headingRef,
  onApplicationUpdated,
  onOfferUnavailableRequested,
  decisionActionsDisabled = false,
}: ReviewQueueApplicationCardProps) {
  const [currentApplication, setCurrentApplication] = useState<Application>(application);
  const [selectedStatus, setSelectedStatus] = useState(application.status);
  const [saving, setSaving] = useState(false);
  const [markingUnavailable, setMarkingUnavailable] = useState(false);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [drawerTab, setDrawerTab] = useState<MotivationTab>('coverLetter');
  const [descriptionExpanded, setDescriptionExpanded] = useState(false);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    setCurrentApplication(application);
    setSelectedStatus(application.status);
    setDrawerOpen(false);
    setDrawerTab('coverLetter');
    setDescriptionExpanded(false);
    setNotice('');
    setError('');
  }, [application]);

  const applyUpdatedApplication = (updated: Application): void => {
    setCurrentApplication(updated);
    setSelectedStatus(updated.status);
    onApplicationUpdated?.(updated);
  };

  const saveApplication = async (status: string, successMessage: string): Promise<void> => {
    if (saving || decisionActionsDisabled) return;

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

  const markOfferUnavailable = async (): Promise<void> => {
    if (decisionActionsDisabled || markingUnavailable || saving) return;

    if (onOfferUnavailableRequested) {
      onOfferUnavailableRequested();
      return;
    }

    setMarkingUnavailable(true);
    setNotice('');
    setError('');

    try {
      const updated = await api<Application>(`/applications/${currentApplication.id}/offer-unavailable`, {
        method: 'POST',
      });
      applyUpdatedApplication(updated);
    } catch (caughtError: unknown) {
      setError(getErrorMessage(caughtError));
    } finally {
      setMarkingUnavailable(false);
    }
  };

  const openMotivationDrawer = (tab: MotivationTab): void => {
    setDrawerTab(tab);
    setDrawerOpen(true);
  };

  const job = currentApplication.jobOffer;
  const profileComparison = (currentApplication as Application & {
    profileComparison?: JobProfileComparison;
  }).profileComparison;
  const editableApplication = currentApplication as EditableApplication;
  const contractLabel = job.contractType?.trim() || 'Non renseigné';
  const isCdi = /(^|\W)cdi($|\W)/i.test(contractLabel);
  const hasMessage = currentApplication.message.trim() !== '';
  const hasCoverLetter = currentApplication.coverLetter.trim() !== '';
  const hasCompensation = (currentApplication.compensationAnswer ?? '').trim() !== '';
  const scoreReasons = job.scoreReasons ?? [];
  const description = jobDescriptionToPlainText(job.description) || 'Description non disponible.';
  const descriptionIsLong = description.length > 1_400 || description.split('\n').length > 18;
  const isLowMatch = job.score < 60;
  const messageCharacters = currentApplication.message.length;
  const messageOverCommonLimit = messageCharacters > 400;
  const coverLetterWords = wordCount(currentApplication.coverLetter);
  const publicationTiming = offerPublicationTiming(job.publishedAt, job.discoveredAt);

  return (
    <article className={styles.card} aria-label={`Offre à examiner : ${job.title}`}>
      <header className={styles.offerHeader}>
        <div className={styles.titleBlock}>
          <div className={styles.eyebrow}>{isLowMatch ? 'À examiner' : 'Prête à envoyer'}</div>
          <h2 ref={headingRef} tabIndex={-1}>{job.title}</h2>
          <div className={styles.meta} aria-label="Métadonnées de l’offre">
            {job.company && <span>{job.company}</span>}
            {job.location && <span>{job.location}</span>}
            {job.workMode && <span>{job.workMode}</span>}
            {job.source && <span>{job.source}</span>}
            <span title={publicationTiming.exactLabel ?? undefined}>{publicationTiming.label}</span>
          </div>
        </div>
        <div className={styles.badges}>
          <Badge tone={applicationStatusTone(currentApplication.status)}>
            {applicationBadgeLabel(currentApplication)}
          </Badge>
          {publicationTiming.stale && <Badge tone="warn">Offre ancienne</Badge>}
          {isLowMatch && <Badge tone="warn">Match faible · {job.score}%</Badge>}
          <Badge tone={isCdi ? 'good' : 'neutral'}>{isCdi ? 'CDI' : 'Non-CDI'}</Badge>
          {!isCdi && contractLabel !== 'Non renseigné' && <Badge>{contractLabel}</Badge>}
        </div>
      </header>

      <ReviewQueueTechnologyComparison comparison={profileComparison} />

      <section className={styles.actionsPanel} aria-label="Actions secondaires sur la candidature">
        <div className={styles.quickActions}>
          {job.sourceUrl && (
            <a className="btn small" href={job.sourceUrl} target="_blank" rel="noreferrer">
              Ouvrir la plateforme
            </a>
          )}

          <button
            className="btn secondary small"
            type="button"
            disabled={saving || markingUnavailable || decisionActionsDisabled}
            onClick={() => void markOfferUnavailable()}
          >
            {markingUnavailable ? 'Enregistrement…' : 'Offre indisponible'}
          </button>

          {currentApplication.cvDocument && (
            <a className="btn secondary small" href={currentApplication.cvDocument.downloadUrl} target="_blank" rel="noreferrer">
              Ouvrir le CV
            </a>
          )}
        </div>

        <div className={styles.statusActions}>
          <label className={styles.statusControl}>
            Statut de suivi
            <select
              aria-label="Statut de suivi dans JobPilot"
              value={selectedStatus}
              disabled={saving || markingUnavailable || decisionActionsDisabled || currentApplication.status === 'SUBMISSION_PENDING'}
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
              || markingUnavailable
              || decisionActionsDisabled
              || currentApplication.status === 'SUBMISSION_PENDING'
              || selectedStatus === currentApplication.status}
            onClick={() => void saveTrackingStatus()}
          >
            {saving ? 'Enregistrement…' : 'Appliquer'}
          </button>
        </div>
      </section>

      {notice !== '' && <div className={`success-box ${styles.feedback}`} role="status">{notice}</div>}
      {error !== '' && <div className={`error-box ${styles.feedback}`} role="alert">{error}</div>}

      <section className={styles.mission} aria-labelledby={`mission-title-${currentApplication.id}`}>
        <div className={styles.sectionHeader}>
          <div className={styles.sectionTitleBlock}>
            <div className={styles.eyebrow}>Contexte de décision</div>
            <h3 id={`mission-title-${currentApplication.id}`}>Description de la mission</h3>
          </div>
        </div>
        <div
          className={`${styles.descriptionViewport} ${descriptionIsLong && !descriptionExpanded ? styles.descriptionViewportCollapsed : ''}`}
          data-expanded={descriptionExpanded ? 'true' : 'false'}
        >
          <div className={styles.description}>{description}</div>
        </div>
        {descriptionIsLong && (
          <button
            className={styles.expandButton}
            type="button"
            aria-expanded={descriptionExpanded}
            onClick={() => setDescriptionExpanded((value) => !value)}
          >
            {descriptionExpanded ? 'Voir moins' : 'Voir toute la description'}
          </button>
        )}
      </section>

      <section className={styles.scorePanel} aria-label="Explication du score de correspondance">
        <div className={styles.scoreValue}>{job.score}%</div>
        <div className={styles.scoreText}>
          <div className={styles.eyebrow}>Matching JobPilot</div>
          <h3>Pourquoi ce score ?</h3>
        </div>
        <ul className={styles.scoreReasons}>
          {scoreReasons.length > 0
            ? scoreReasons.map((reason, reasonIndex) => <li key={`${reason}-${reasonIndex}`}>{reason}</li>)
            : <li>Aucune explication détaillée disponible.</li>}
        </ul>
      </section>

      <section className={styles.applicationContent} aria-labelledby={`application-content-title-${currentApplication.id}`}>
        <div className={styles.sectionHeader}>
          <div className={styles.sectionTitleBlock}>
            <div className={styles.eyebrow}>Candidature</div>
            <h3 id={`application-content-title-${currentApplication.id}`}>Contenu prêt à envoyer</h3>
          </div>
          <span className={styles.coverLetterCount}>{coverLetterWords} mots dans la lettre</span>
        </div>

        <div className={styles.contentGrid}>
          <div className={styles.contentItem}>
            <span>CV</span>
            <strong>{currentApplication.cvDocument?.filename ?? 'Manquant'}</strong>
          </div>
          <div className={styles.contentItem}>
            <span>Message court de motivation</span>
            <strong>{hasMessage ? `${messageCharacters} caractères${messageOverCommonLimit ? ' · à réduire' : ''}` : 'Non préparé'}</strong>
          </div>
          <div className={styles.contentItem}>
            <span>Lettre de motivation</span>
            <strong>{hasCoverLetter ? (editableApplication.coverLetterManuallyEdited ? 'Modifiée manuellement' : 'Prête') : 'Non préparée'}</strong>
          </div>
          <div className={styles.contentItem}>
            <span>Rémunération</span>
            <strong>{hasCompensation ? currentApplication.compensationAnswer : 'Non préparée'}</strong>
          </div>
        </div>

        <div className={styles.contentActions}>
          <button className="btn small" type="button" onClick={() => openMotivationDrawer('coverLetter')}>
            Ouvrir les textes de motivation
          </button>
          <button className="btn secondary small" type="button" onClick={() => openMotivationDrawer('message')}>
            Message court
          </button>
        </div>
      </section>

      <CoverLetterDrawer
        application={currentApplication}
        open={drawerOpen}
        initialTab={drawerTab}
        onClose={() => setDrawerOpen(false)}
        onApplicationUpdated={applyUpdatedApplication}
      />
    </article>
  );
}
