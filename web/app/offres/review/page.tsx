'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useState } from 'react';

import { ReviewQueueApplicationCard } from '@/components/ReviewQueueApplicationCard';
import { Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import { matchesOfferInboxView } from '@/lib/offer-inbox';
import {
  clampReviewQueueIndex,
  currentReviewQueueItem,
  nextReviewQueueIndexAfterDecision,
} from '@/lib/review-queue';
import type { Application } from '@/lib/types';

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
    () => (applications ?? []).filter((application) => matchesOfferInboxView(application, 'actionable')),
    [applications],
  );

  const currentIndex = clampReviewQueueIndex(index, queue.length);
  const current = currentReviewQueueItem(queue, currentIndex);
  const progress = queue.length > 0 ? ((currentIndex + 1) / queue.length) * 100 : 0;

  const updateApplication = useCallback((updated: Application): void => {
    const completedCurrentDecision = current?.id === updated.id
      && !matchesOfferInboxView(updated, 'actionable');

    if (completedCurrentDecision) {
      setIndex(nextReviewQueueIndexAfterDecision(currentIndex, queue.length));
    }

    setApplications((items) => items?.map((application) => (
      application.id === updated.id ? updated : application
    )) ?? items);
  }, [current?.id, currentIndex, queue.length]);

  return (
    <>
      <PageHeader
        title="Review Queue"
        description="Décide rapidement, une offre à la fois, avec tout le contexte visible sans ouvrir un panneau secondaire."
        actions={<Link className="btn secondary" href="/offres">Retour aux offres</Link>}
      />

      {error !== '' && <ErrorBox message={error} />}

      {applications === null ? (
        <Card><Loading /></Card>
      ) : queue.length === 0 ? (
        <Card>
          <Empty>Aucune offre à traiter dans la Review Queue.</Empty>
        </Card>
      ) : current ? (
        <div className="review-queue-workspace">
          <nav className="review-queue-slider" aria-label="Navigation dans la Review Queue">
            <button
              className="btn secondary small"
              type="button"
              disabled={currentIndex === 0}
              onClick={() => setIndex(currentIndex - 1)}
            >
              ← Précédente
            </button>

            <div className="review-queue-slider-center">
              <div className="review-queue-slider-label">
                <strong>Offre {currentIndex + 1}</strong>
                <span>sur {queue.length}</span>
              </div>
              <div className="review-queue-slider-track" aria-hidden="true">
                <span style={{ width: `${progress}%` }} />
              </div>
              <div className="review-queue-slider-hint">
                Une décision finale retire l’offre de la queue et ouvre automatiquement la suivante.
              </div>
            </div>

            <button
              className="btn secondary small"
              type="button"
              disabled={currentIndex >= queue.length - 1}
              onClick={() => setIndex(currentIndex + 1)}
            >
              Suivante →
            </button>
          </nav>

          <ReviewQueueApplicationCard
            application={current}
            onApplicationUpdated={updateApplication}
          />
        </div>
      ) : null}
    </>
  );
}
