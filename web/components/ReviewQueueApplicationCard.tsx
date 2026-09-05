'use client';

import type { Ref } from 'react';
import { useEffect, useState } from 'react';

import { CoverLetterDrawer } from '@/components/CoverLetterDrawer';
import styles from '@/components/ReviewQueueApplicationCard.module.css';
import { ReviewQueueTechnologyComparison, type JobProfileComparison } from '@/components/ReviewQueueTechnologyComparison';
import { Badge, Button, ErrorBox, InlineFeedback } from '@/components/UI';
import { api } from '@/lib/api';
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

const MESSAGE_RECOMMENDED_LIMIT = 400;
const DESCRIPTION_PREVIEW_LENGTH = 760;

function wordCount(value: string): number {
  const normalized = value.trim();
  return normalized === '' ? 0 : normalized.split(/\s+/).length;
}

function descriptionPreview(value: string): string {
  if (value.length <= DESCRIPTION_PREVIEW_LENGTH) return value;

  const candidate = value.slice(0, DESCRIPTION_PREVIEW_LENGTH);
  const sentenceEnd = Math.max(candidate.lastIndexOf('. '), candidate.lastIndexOf('\n'));
  const wordEnd = candidate.lastIndexOf(' ');
  const cutAt = sentenceEnd > DESCRIPTION_PREVIEW_LENGTH * 0.55
    ? sentenceEnd + 1
    : wordEnd > 0 ? wordEnd : DESCRIPTION_PREVIEW_LENGTH;

  return `${candidate.slice(0, cutAt).trim()}…`;
}

function humanizeScoreReason(reason: string): string {
  const value = reason.trim();
  const analysisMatch = value.match(/^Analyse IA\s*:\s*(MATCH|NO_MATCH)(?:\s*[·-]\s*confiance\s*(\d+)\s*%?)?/i);

  if (analysisMatch) {
    const decision = analysisMatch[1].toUpperCase() === 'MATCH' ? 'Correspondance forte' : 'Correspondance à vérifier';
    return analysisMatch[2] ? `${decision} · confiance ${analysisMatch[2]} %` : decision;
  }

  return value
    .replace(/^Rôle principal détecté par IA\s*:\s*/i, 'Rôle principal : ')
    .replace(/^Stack principale détectée par IA\s*:\s*/i, 'Compétences clés : ')
    .replace(/^Positionnement ([^:]+?) détecté par IA\s*:\s*/i, 'Positionnement $1 : ')
    .replace(/^Explication IA\s*:\s*/i, '');
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
    await saveApplication(selectedStatus, 'Statut de suivi enregistré.');
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
  const hasCv = Boolean(currentApplication.cvDocument);
  const scoreReasons = job.scoreReasons ?? [];
  const description = jobDescriptionToPlainText(job.description) || 'Description non disponible.';
  const compactDescription = descriptionPreview(description);
  const descriptionIsLong = compactDescription !== description;
  const visibleDescription = descriptionExpanded ? description : compactDescription;
  const isLowMatch = job.score < 60;
  const messageCharacters = currentApplication.message.length;
  const messageOverCommonLimit = messageCharacters > MESSAGE_RECOMMENDED_LIMIT;
  const coverLetterWords = wordCount(currentApplication.coverLetter);
  const publicationTiming = offerPublicationTiming(job.publishedAt, job.discoveredAt);
  const readinessAttentionCount = [
    !hasCv,
    !hasMessage || messageOverCommonLimit,
    !hasCoverLetter,
    !hasCompensation,
  ].filter(Boolean).length;

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
              Ouvrir l’offre
            </a>
          )}

          <Button
            variant="secondary"
            size="small"
            loading={markingUnavailable}
            disabled={saving || decisionActionsDisabled}
            onClick={() => void markOfferUnavailable()}
          >
            {markingUnavailable ? 'Enregistrement…' : 'Marquer comme indisponible'}
          </Button>

          {currentApplication.cvDocument && (
            <a className="btn secondary small" href={currentApplication.cvDocument.downloadUrl} target="_blank" rel="noreferrer">
              Ouvrir le CV
            </a>
          )}
        </div>

        <div className={styles.statusActions}>
          <label className={styles.statusControl}>
            Suivi
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

          <Button
            variant="secondary"
            size="small"
            loading={saving}
            disabled={markingUnavailable
              || decisionActionsDisabled
              || currentApplication.status === 'SUBMISSION_PENDING'
              || selectedStatus === currentApplication.status}
            onClick={() => void saveTrackingStatus()}
          >
            {saving ? 'Enregistrement…' : 'Enregistrer'}
          </Button>
        </div>
      </section>

      {notice !== '' && <InlineFeedback className={styles.feedback} tone="success">{notice}</InlineFeedback>}
      {error !== '' && (
        <div className={styles.feedback}>
          <ErrorBox
            title="Modification non enregistrée"
            message={error}
            impact="La candidature n’a pas été modifiée."
          />
        </div>
      )}

      <section className={styles.mission} aria-labelledby={`mission-title-${currentApplication.id}`}>
        <div className={styles.sectionHeader}>
          <div className={styles.sectionTitleBlock}>
            <div className={styles.eyebrow}>Mission</div>
            <h3 id={`mission-title-${currentApplication.id}`}>Résumé de la mission</h3>
          </div>
        </div>
        <div className={styles.descriptionViewport} data-expanded={descriptionExpanded ? 'true' : 'false'}>
          <div className={styles.description}>{visibleDescription}</div>
        </div>
        {descriptionIsLong && (
          <button
            className={styles.expandButton}
            type="button"
            aria-expanded={descriptionExpanded}
            onClick={() => setDescriptionExpanded((value) => !value)}
          >
            {descriptionExpanded ? 'Réduire' : 'Voir le détail'}
          </button>
        )}
      </section>

      <section className={`${styles.scorePanel} ${isLowMatch ? styles.scorePanelLow : ''}`} aria-labelledby={`score-title-${currentApplication.id}`}>
        <div className={styles.scoreSummary}>
          <div className={styles.scoreValue}>{job.score}%</div>
          <div className={styles.scoreHeader}>
            <div>
              <div className={styles.eyebrow}>Matching JobPilot</div>
              <h3 id={`score-title-${currentApplication.id}`}>Pourquoi cette note ?</h3>
              {isLowMatch && <div className={styles.lowMatchHint}>Correspondance faible : vérification recommandée avant envoi.</div>}
            </div>
          </div>
        </div>

        {scoreReasons.length > 0 ? (
          <ul className={styles.scoreReasons}>
            {scoreReasons.map((reason) => <li key={reason}>{humanizeScoreReason(reason)}</li>)}
          </ul>
        ) : (
          <div className="muted">Aucune explication détaillée disponible.</div>
        )}
      </section>

      <section className={styles.applicationSummary} aria-labelledby={`application-summary-title-${currentApplication.id}`}>
        <div className={styles.applicationSummaryHeader}>
          <div>
            <div className={styles.eyebrow}>Candidature</div>
            <h3 id={`application-summary-title-${currentApplication.id}`}>Candidature prête</h3>
          </div>
          <Badge tone={readinessAttentionCount === 0 ? 'good' : 'warn'}>
            {readinessAttentionCount === 0
              ? 'Prête à envoyer'
              : `${readinessAttentionCount} point${readinessAttentionCount > 1 ? 's' : ''} à vérifier`}
          </Badge>
        </div>

        <div className={styles.applicationContent}>
          <div className={styles.applicationDocuments}>
            <div className={styles.applicationDocument}>
              <span>CV</span>
              <strong>{hasCv ? `Prêt · ${currentApplication.cvDocument?.name}` : 'À sélectionner'}</strong>
            </div>
            <div className={styles.applicationDocument}>
              <span>Message court</span>
              <strong>
                {!hasMessage
                  ? 'À préparer'
                  : messageOverCommonLimit
                    ? `À raccourcir · ${messageCharacters} caractères`
                    : `Prêt · ${messageCharacters} caractères`}
              </strong>
            </div>
            <div className={styles.applicationDocument}>
              <span>Lettre de motivation</span>
              <strong>
                {hasCoverLetter
                  ? `Prête${editableApplication.coverLetterManuallyEdited ? ' · modifiée' : ''} · ${coverLetterWords} mots`
                  : 'À préparer'}
              </strong>
            </div>
            <div className={styles.applicationDocument}>
              <span>Rémunération</span>
              <strong>{hasCompensation ? `Renseignée · ${currentApplication.compensationAnswer}` : 'Non renseignée'}</strong>
            </div>
          </div>

          {(hasMessage || hasCoverLetter) && (
            <div className={styles.applicationActions}>
              {hasCoverLetter && (
                <button className="btn small" type="button" onClick={() => openMotivationDrawer('coverLetter')}>
                  Voir les textes
                </button>
              )}
              {!hasCoverLetter && hasMessage && (
                <button className="btn small" type="button" onClick={() => openMotivationDrawer('message')}>
                  Voir le message
                </button>
              )}
              {hasMessage && hasCoverLetter && (
                <button className="btn secondary small" type="button" onClick={() => openMotivationDrawer('message')}>
                  Message court
                </button>
              )}
            </div>
          )}
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