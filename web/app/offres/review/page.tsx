'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { ApplicationGoalsPanel } from '@/components/ApplicationGoalsPanel';
import { ReviewQueueApplicationCard } from '@/components/ReviewQueueApplicationCard';
import { Button, Card, Empty, ErrorBox, Loading } from '@/components/UI';
import { api } from '@/lib/api';
import { crmOrganizationHref } from '@/lib/crm-navigation';
import { getErrorMessage } from '@/lib/errors';
import {
  buildReviewQueue,
  clampReviewQueueIndex,
  currentReviewQueueItem,
  isReadyToSubmitReviewItem,
  nextReviewQueueIndexAfterDecision,
} from '@/lib/review-queue';
import type { Application, Job } from '@/lib/types';

import styles from './review.module.css';

function isInteractiveTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false;

  return target.isContentEditable
    || ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON', 'A'].includes(target.tagName);
}

type ReviewDecision = 'IGNORED_NOT_MATCH' | 'OFFER_UNAVAILABLE' | 'SUBMITTED' | 'ALREADY_APPLIED';

type UndoableDecision = {
  applicationId: number;
  decision: ReviewDecision;
  previousIndex: number;
  jobTitle: string;
};

function decisionFeedback(decision: ReviewDecision): string {
  if (decision === 'IGNORED_NOT_MATCH') return 'Offre marquée « Ne correspond pas ».';
  if (decision === 'OFFER_UNAVAILABLE') return 'Offre marquée indisponible.';
  if (decision === 'ALREADY_APPLIED') return 'Candidature marquée « Déjà postulé ».';
  return 'Candidature marquée envoyée.';
}

export default function ReviewQueuePage() {
  const [applications, setApplications] = useState<Application[] | null>(null);
  const [jobs, setJobs] = useState<Job[] | null>(null);
  const [index, setIndex] = useState(0);
  const [error, setError] = useState('');
  const [decisionSaving, setDecisionSaving] = useState<ReviewDecision | null>(null);
  const [undoSaving, setUndoSaving] = useState(false);
  const [undoableDecision, setUndoableDecision] = useState<UndoableDecision | null>(null);
  const [decisionError, setDecisionError] = useState('');
  const [goalRefreshKey, setGoalRefreshKey] = useState(0);
  const offerHeadingRef = useRef<HTMLHeadingElement>(null);
  const previousCurrentIdRef = useRef<number | null>(null);
  const currentIndexRef = useRef(0);
  const queueLengthRef = useRef(0);

  const load = useCallback(async (): Promise<void> => {
    try {
      const [applicationResult, jobResult] = await Promise.all([
        api<Application[]>('/applications'),
        api<Job[]>('/jobs'),
      ]);
      setApplications(applicationResult);
      setJobs(jobResult);
      setError('');
    } catch (caughtError: unknown) {
      setApplications([]);
      setJobs([]);
      setError(getErrorMessage(caughtError));
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const queue = useMemo(
    () => buildReviewQueue(applications ?? [], jobs ?? []),
    [applications, jobs],
  );

  const loading = applications === null || jobs === null;
  const currentIndex = clampReviewQueueIndex(index, queue.length);
  const current = currentReviewQueueItem(queue, currentIndex);
  const progress = queue.length > 0 ? ((currentIndex + 1) / queue.length) * 100 : 0;
  const currentId = current?.id ?? null;
  const crmContextName = current?.jobOffer.clientName?.trim()
    || current?.jobOffer.company?.trim()
    || '';
  const crmContextHref = crmOrganizationHref(crmContextName);
  const decisionBusy = decisionSaving !== null || undoSaving;
  currentIndexRef.current = currentIndex;
  queueLengthRef.current = queue.length;

  useEffect(() => {
    const previousCurrentId = previousCurrentIdRef.current;
    previousCurrentIdRef.current = currentId;

    if (previousCurrentId === null || currentId === null || previousCurrentId === currentId) {
      return;
    }

    const frame = window.requestAnimationFrame(() => {
      offerHeadingRef.current?.focus();
    });

    return () => window.cancelAnimationFrame(frame);
  }, [currentId]);

  const goPrevious = useCallback((): void => {
    setIndex((value) => Math.max(0, value - 1));
  }, []);

  const goNext = useCallback((): void => {
    setIndex((value) => Math.min(Math.max(0, queueLengthRef.current - 1), value + 1));
  }, []);

  useEffect(() => {
    const navigateWithKeyboard = (event: KeyboardEvent): void => {
      if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || isInteractiveTarget(event.target)) {
        return;
      }

      if (event.key === 'ArrowLeft' && currentIndexRef.current > 0) {
        event.preventDefault();
        goPrevious();
      }

      if (event.key === 'ArrowRight' && currentIndexRef.current < queueLengthRef.current - 1) {
        event.preventDefault();
        goNext();
      }
    };

    window.addEventListener('keydown', navigateWithKeyboard);
    return () => window.removeEventListener('keydown', navigateWithKeyboard);
  }, [goNext, goPrevious]);

  const replaceApplication = useCallback((updated: Application): void => {
    setApplications((items) => items?.map((application) => (
      application.id === updated.id ? updated : application
    )) ?? items);
  }, []);

  const updateApplication = useCallback((updated: Application): void => {
    const completedCurrentDecision = current?.id === updated.id
      && !isReadyToSubmitReviewItem(updated);

    if (completedCurrentDecision) {
      setIndex(nextReviewQueueIndexAfterDecision(currentIndex, queue.length));
    }

    replaceApplication(updated);
  }, [current?.id, currentIndex, queue.length, replaceApplication]);

  const refreshGoals = useCallback((): void => {
    setGoalRefreshKey((value) => value + 1);
    window.dispatchEvent(new Event('jobpilot:application-goals-changed'));
  }, []);

  const persistDecision = useCallback(async (decision: ReviewDecision): Promise<void> => {
    if (!current || decisionBusy) return;

    setDecisionSaving(decision);
    setDecisionError('');

    try {
      const status = decision === 'ALREADY_APPLIED' ? 'SUBMITTED' : decision;
      const updated = decision === 'OFFER_UNAVAILABLE'
        ? await api<Application>(`/applications/${current.id}/offer-unavailable`, {
            method: 'POST',
          })
        : await api<Application>(`/applications/${current.id}`, {
            method: 'PATCH',
            body: JSON.stringify({
              status,
              ...(decision === 'ALREADY_APPLIED' ? { channel: 'Candidature externe' } : {}),
              message: current.message,
              coverLetter: current.coverLetter,
              compensationAnswer: current.compensationAnswer,
              confirmationRef: current.confirmationRef,
            }),
          });

      setUndoableDecision({
        applicationId: current.id,
        decision,
        previousIndex: currentIndex,
        jobTitle: current.jobOffer.title,
      });
      updateApplication(updated);
      if (status === 'SUBMITTED') {
        refreshGoals();
      }
    } catch (caughtError: unknown) {
      setDecisionError(getErrorMessage(caughtError));
    } finally {
      setDecisionSaving(null);
    }
  }, [current, currentIndex, decisionBusy, refreshGoals, updateApplication]);

  const undoLastDecision = useCallback(async (): Promise<void> => {
    if (!undoableDecision || decisionBusy) return;

    setUndoSaving(true);
    setDecisionError('');

    try {
      const updated = await api<Application>(
        `/applications/${undoableDecision.applicationId}/review-decision/undo`,
        { method: 'POST' },
      );
      replaceApplication(updated);
      setIndex(undoableDecision.previousIndex);
      if (undoableDecision.decision === 'SUBMITTED' || undoableDecision.decision === 'ALREADY_APPLIED') {
        refreshGoals();
      }
      setUndoableDecision(null);
    } catch (caughtError: unknown) {
      setDecisionError(getErrorMessage(caughtError));
    } finally {
      setUndoSaving(false);
    }
  }, [decisionBusy, refreshGoals, replaceApplication, undoableDecision]);

  const accessibleQueueStatus = loading
    ? 'Chargement de la Review Queue.'
    : current
      ? `Offre ${currentIndex + 1} sur ${queue.length} : ${current.jobOffer.title}`
      : 'Aucune candidature prête à envoyer dans la Review Queue.';

  return (
    <div className="review-queue-page">
      <header className="review-queue-compact-header">
        <div className="review-queue-compact-title">
          <h1>Review Queue</h1>
          <span aria-live="polite">
            {loading ? 'Chargement' : `${queue.length} prête${queue.length > 1 ? 's' : ''} à envoyer`}
          </span>
        </div>
        <div className="actions">
          {crmContextHref && (
            <Link
              className="btn secondary small"
              href={crmContextHref}
              aria-label={`Ouvrir le contexte CRM de ${crmContextName}`}
            >
              Contexte CRM
            </Link>
          )}
          <Link className="review-queue-back-link" href="/offres">← Offres</Link>
        </div>
      </header>

      <ApplicationGoalsPanel refreshKey={goalRefreshKey} />

      <div className={styles.screenReaderStatus} role="status" aria-live="polite" aria-atomic="true">
        {accessibleQueueStatus}
      </div>

      {undoableDecision && (
        <div className={styles.undoNotice} role="status" aria-live="polite">
          <span>{decisionFeedback(undoableDecision.decision)}</span>
          <button
            className={styles.undoButton}
            type="button"
            disabled={decisionBusy}
            aria-label={`Annuler la dernière action sur ${undoableDecision.jobTitle}`}
            onClick={() => void undoLastDecision()}
          >
            ↶ {undoSaving ? 'Annulation…' : 'Annuler'}
          </button>
        </div>
      )}

      {decisionError !== '' && (
        <div className={styles.globalDecisionError} role="alert">{decisionError}</div>
      )}

      {error !== '' && <ErrorBox message={error} />}

      {loading ? (
        <Card><Loading /></Card>
      ) : queue.length === 0 ? (
        <Card>
          <Empty>Aucune candidature prête à envoyer dans la Review Queue.</Empty>
        </Card>
      ) : current ? (
        <div className={`review-queue-workspace ${styles.workspace}`}>
          <ReviewQueueApplicationCard
            application={current}
            headingRef={offerHeadingRef}
            onApplicationUpdated={updateApplication}
            onOfferUnavailableRequested={() => void persistDecision('OFFER_UNAVAILABLE')}
            decisionActionsDisabled={decisionBusy}
          />

          <nav className={styles.decisionBar} aria-label="Décision et navigation dans la Review Queue">
            <button
              className={`${styles.decisionButton} ${styles.rejectButton}`}
              type="button"
              disabled={decisionBusy}
              onClick={() => void persistDecision('IGNORED_NOT_MATCH')}
            >
              <span aria-hidden="true">✕</span>
              <span>{decisionSaving === 'IGNORED_NOT_MATCH' ? 'Enregistrement…' : 'Ne correspond pas'}</span>
            </button>

            <button
              className={`${styles.decisionButton} ${styles.unavailableButton}`}
              type="button"
              disabled={decisionBusy}
              onClick={() => void persistDecision('OFFER_UNAVAILABLE')}
            >
              <span aria-hidden="true">⊘</span>
              <span>{decisionSaving === 'OFFER_UNAVAILABLE' ? 'Enregistrement…' : 'N’est plus disponible'}</span>
            </button>

            <div className={styles.secondaryNavigation}>
              <Button
                className={styles.navButton}
                variant="secondary"
                size="small"
                aria-label="Précédente"
                aria-keyshortcuts="ArrowLeft"
                title="Précédente — flèche gauche"
                disabled={currentIndex === 0 || decisionBusy}
                onClick={goPrevious}
              >
                ← <span>Préc.</span>
              </Button>

              <div className={styles.progressBlock}>
                <div className={styles.progressLabel}>
                  <strong>{currentIndex + 1} / {queue.length}</strong>
                  <span>← →</span>
                </div>
                <div className={styles.progressTrack} aria-hidden="true">
                  <span style={{ width: `${progress}%` }} />
                </div>
              </div>

              <Button
                className={styles.navButton}
                variant="secondary"
                size="small"
                aria-label="Suivante"
                aria-keyshortcuts="ArrowRight"
                title="Suivante — flèche droite"
                disabled={currentIndex >= queue.length - 1 || decisionBusy}
                onClick={goNext}
              >
                <span>Suiv.</span> →
              </Button>
            </div>

            <button
              className={`${styles.decisionButton} ${styles.alreadyAppliedButton}`}
              type="button"
              disabled={decisionBusy}
              onClick={() => void persistDecision('ALREADY_APPLIED')}
            >
              <span aria-hidden="true">↗</span>
              <span>{decisionSaving === 'ALREADY_APPLIED' ? 'Enregistrement…' : 'Déjà postulé'}</span>
            </button>

            <button
              className={`${styles.decisionButton} ${styles.sentButton}`}
              type="button"
              disabled={decisionBusy}
              onClick={() => void persistDecision('SUBMITTED')}
            >
              <span aria-hidden="true">✓</span>
              <span>{decisionSaving === 'SUBMITTED' ? 'Enregistrement…' : 'Envoyée'}</span>
            </button>
          </nav>
        </div>
      ) : null}
    </div>
  );
}
