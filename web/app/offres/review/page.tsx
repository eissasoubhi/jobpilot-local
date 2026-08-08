'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useState } from 'react';

import { ReviewQueueApplicationCard } from '@/components/ReviewQueueApplicationCard';
import { Card, Empty, ErrorBox, Loading } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import {
  clampReviewQueueIndex,
  currentReviewQueueItem,
  isReadyToSubmitReviewItem,
  nextReviewQueueIndexAfterDecision,
} from '@/lib/review-queue';
import type { Application } from '@/lib/types';

function isInteractiveTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false;

  return target.isContentEditable
    || ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON', 'A'].includes(target.tagName);
}

export default function ReviewQueuePage() {
  const [applications, setApplications] = useState<Application[] | null>(null);
  const [index, setIndex] = useState(0);
  const [error, setError] = useState('');

  const load = useCallback(async (): Promise<void> => {
    try {
      const result = await api<Application[]>('/applications');
      setApplications(result);
      setError('');
    } catch (caughtError: unknown) {
      setApplications([]);
      setError(getErrorMessage(caughtError));
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const queue = useMemo(
    () => (applications ?? []).filter(isReadyToSubmitReviewItem),
    [applications],
  );

  const currentIndex = clampReviewQueueIndex(index, queue.length);
  const current = currentReviewQueueItem(queue, currentIndex);
  const progress = queue.length > 0 ? ((currentIndex + 1) / queue.length) * 100 : 0;

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

  return (
    <div className="review-queue-page">
      <header className="review-queue-compact-header">
        <div className="review-queue-compact-title">
          <h1>Review Queue</h1>
          <span aria-live="polite">
            {applications === null ? 'Chargement' : `${queue.length} prête${queue.length > 1 ? 's' : ''} à envoyer`}
          </span>
        </div>
        <Link className="review-queue-back-link" href="/offres">← Offres</Link>
      </header>

      {error !== '' && <ErrorBox message={error} />}

      {applications === null ? (
        <Card><Loading /></Card>
      ) : queue.length === 0 ? (
        <Card>
          <Empty>Aucune candidature prête à envoyer dans la Review Queue.</Empty>
        </Card>
      ) : current ? (
        <div className="review-queue-workspace">
          <ReviewQueueApplicationCard
            application={current}
            onApplicationUpdated={updateApplication}
          />

          <nav className="review-queue-slider" aria-label="Navigation dans la Review Queue">
            <button
              className="btn secondary small review-queue-nav-button"
              type="button"
              aria-label="Précédente"
              aria-keyshortcuts="ArrowLeft"
              title="Précédente — flèche gauche"
              disabled={currentIndex === 0}
              onClick={goPrevious}
            >
              <span aria-hidden="true">←</span>
              <span className="review-queue-nav-text">Précédente</span>
            </button>

            <div className="review-queue-slider-center">
              <div className="review-queue-slider-label">
                <strong>{currentIndex + 1} / {queue.length}</strong>
                <span className="review-queue-keyboard-hint">Clavier : ← →</span>
              </div>
              <div className="review-queue-slider-track" aria-hidden="true">
                <span style={{ width: `${progress}%` }} />
              </div>
            </div>

            <button
              className="btn secondary small review-queue-nav-button"
              type="button"
              aria-label="Suivante"
              aria-keyshortcuts="ArrowRight"
              title="Suivante — flèche droite"
              disabled={currentIndex >= queue.length - 1}
              onClick={goNext}
            >
              <span className="review-queue-nav-text">Suivante</span>
              <span aria-hidden="true">→</span>
            </button>
          </nav>
        </div>
      ) : null}
    </div>
  );
}
