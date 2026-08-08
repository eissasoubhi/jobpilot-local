'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useState } from 'react';

import { OfferApplicationSummary } from '@/components/OfferApplicationSummary';
import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import { getErrorMessage } from '@/lib/errors';
import { matchesOfferInboxView } from '@/lib/offer-inbox';
import { clampReviewQueueIndex, currentReviewQueueItem } from '@/lib/review-queue';
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

  const updateApplication = useCallback((updated: Application): void => {
    setApplications((items) => items?.map((application) => (
      application.id === updated.id ? updated : application
    )) ?? items);
  }, []);

  return (
    <>
      <PageHeader
        title="Review Queue"
        description="Traite les candidatures préparées une par une, sans perdre le contexte de l’offre."
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
        <>
          <Card>
            <div className="actions" style={{ justifyContent: 'space-between', alignItems: 'center' }}>
              <div className="actions">
                <Badge tone="blue">Review Queue</Badge>
                <Badge>{currentIndex + 1} / {queue.length}</Badge>
              </div>
              <div className="actions">
                <button
                  className="btn secondary small"
                  type="button"
                  disabled={currentIndex === 0}
                  onClick={() => setIndex(currentIndex - 1)}
                >
                  Précédente
                </button>
                <button
                  className="btn secondary small"
                  type="button"
                  disabled={currentIndex >= queue.length - 1}
                  onClick={() => setIndex(currentIndex + 1)}
                >
                  Suivante
                </button>
              </div>
            </div>
            <div className="small muted" style={{ marginTop: 8 }}>
              La queue utilise uniquement les candidatures encore actionnables. Une candidature envoyée ou ignorée quitte cette vue dès que son statut est enregistré.
            </div>
          </Card>

          <Card>
            <h2 style={{ marginTop: 0 }}>{current.jobOffer.title}</h2>
            <div className="small muted">
              {current.jobOffer.company || 'Entreprise non renseignée'} · {current.jobOffer.location || 'Lieu non renseigné'}
            </div>
            <OfferApplicationSummary
              application={current}
              onApplicationUpdated={updateApplication}
            />
          </Card>
        </>
      ) : null}
    </>
  );
}
