'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { ReviewQueueApplicationCard } from '@/components/ReviewQueueApplicationCard';
import { Card, Empty, ErrorBox, Loading } from '@/components/UI';
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

type ReviewDecision = 'IGNORED_NOT_MATCH' | 'SUBMITTED';

export default function ReviewQueuePage() {
  const [applications, setApplications] = useState<Application[] | null>(null);
  const [jobs, setJobs] = useState<Job[] | null>(null);
  const [index, setIndex] = useState(0);
  const [error, setError] = useState('');
  const [decisionSaving, setDecisionSaving] = useState<ReviewDecision | null>(null);
  const [decisionError, setDecisionError] = useState('');
  const offerHeadingRef = useRef<HTMLHeadingElement>(null);
  const previousCurrentIdRef = useRef<number | null>(null);

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
    if (currentIndex > 0) setIndex(currentIndex - 1);
  }, [currentIndex]);

  const goNext = useCallback((): void => {
    if (currentIndex < queue.length - 1) setIndex(currentIndex + 1);
  }, [currentIndex, queue.length]);

  useEffect(() => {
    const navigateWithKeyboard = (event: KeyboardEvent): void => {
      if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || isInteractiveTarget(event.target)) {
        return;
      }

      if (event.key === 'ArrowLeft' && currentIndex > 0) {
        event.preventDefault();
        goPrevious();
      }

      if (event.key === 'ArrowRight' && currentIndex < queue.length - 1) {
        event.preventDefault();
        goNext();
      }
    };

    window.addEventListener('keydown', navigateWithKeyboard);
    return () => window.removeEventListener('keydown', navigateWithKeyboard);
  }, [currentIndex, goNext, goPrevious, queue.length]);

  const updateApplication = useCallback((updated: Application): void => {
    const completedCurrentDecision = current?.id === updated.id
      && !isReadyToSubmitReviewItem(updated);

    if (completedCurrentDecision) {
      setIndex(nextReviewQueueIndexAfterDecision(currentIndex, queue.length));
    }

    setApplications((items) => items?.map((application) => (
      application.id === updated.id ? updated : application
    )) ?? items);
  }, [current?.id, currentIndex, queue.length]);

  const persistDecision = useCallback(async (status: ReviewDecision): Promise<void> => {
    if (!current || decisionSaving !== null) return;

    setDecisionSaving(status);
    setDecisionError('');

    try {
      const updated = await api<Application>(`/applications/${current.id}`, {
        method: 'PATCH',
        body: JSON.stringify({
          status,
          message: current.message,
          coverLetter: current.coverLetter,
          compensationAnswer: current.compensationAnswer,
          confirmationRef: current.confirmationRef,
        }),
      });
      updateApplication(updated);
    } catch (caughtError: unknown) {
      setDecisionError(getErrorMessage(caughtError));
    } finally {
      setDecisionSaving(null);
    }
  }, [current, decisionSaving, updateApplication]);

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

      <div className={styles.screenReaderStatus} role="status" aria-live="polite" aria-atomic="true">
        {accessibleQueueStatus}
      </div>

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
          />

          <nav className={styles.decisionBar} aria-label="Décision et navigation dans la Review Queue">
            <button
              className={`${styles.decisionButton} ${styles.rejectButton}`}
              type="button"
              disabled={decisionSaving !== null}
              onClick={() => void persistDecision('IGNORED_NOT_MATCH')}
            >
              <span aria-hidden="true">✕</span>
              <span>{decisionSaving === 'IGNORED_NOT_MATCH' ? 'Enregistrement…' : 'Ne correspond pas'}</span>
            </button>

            <div className={styles.secondaryNavigation}>
              <button
                className={styles.navButton}
                type="button"
                aria-label="Précédente"
                aria-keyshortcuts="ArrowLeft"
                title="Précédente — flèche gauche"
                disabled={currentIndex === 0 || decisionSaving !== null}
                onClick={goPrevious}
              >
                ← <span>Préc.</span>
              </button>

              <div className={styles.progressBlock}>
                <div className={styles.progressLabel}>
                  <strong>{currentIndex + 1} / {queue.length}</strong>
                  <span>← →</span>
                </div>
                <div className={styles.progressTrack} aria-hidden="true">
                  <span style={{ width: `${progress}%` }} />
                </div>
              </div>

              <button
                className={styles.navButton}
                type="button"
                aria-label="Suivante"
                aria-keyshortcuts="ArrowRight"
                title="Suivante — flèche droite"
                disabled={currentIndex >= queue.length - 1 || decisionSaving !== null}
                onClick={goNext}
              >
                <span>Suiv.</span> →
              </button>
            </div>

            <button
              className={`${styles.decisionButton} ${styles.sentButton}`}
              type="button"
              disabled={decisionSaving !== null}
              onClick={() => void persistDecision('SUBMITTED')}
            >
              <span aria-hidden="true">✓</span>
              <span>{decisionSaving === 'SUBMITTED' ? 'Enregistrement…' : 'Envoyée'}</span>
            </button>

            {decisionError !== '' && (
              <div className={styles.decisionError} role="alert">{decisionError}</div>
            )}
          </nav>
        </div>
      ) : null}
    </div>
  );
}
