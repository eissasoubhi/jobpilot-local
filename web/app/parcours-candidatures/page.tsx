'use client';

import { useEffect, useMemo, useState } from 'react';

import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import {
  presentJobTimeline,
  type PersistedJobTimelineEvent,
} from '@/lib/application-timeline';
import { getErrorMessage } from '@/lib/errors';
import type { Application } from '@/lib/types';

function companyName(application: Application): string {
  return application.jobOffer.company || application.jobOffer.clientName || 'Entreprise non renseignée';
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

export default function ApplicationTimelinePage() {
  const [applications, setApplications] = useState<Application[] | null>(null);
  const [timelineState, setTimelineState] = useState<{
    jobOfferId: number;
    events: PersistedJobTimelineEvent[];
  } | null>(null);
  const [selectedId, setSelectedId] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;

    void api<Application[]>('/applications')
      .then((loadedApplications) => {
        if (!active) return;
        setApplications(loadedApplications);
        setSelectedId((current) => current || (loadedApplications[0] ? String(loadedApplications[0].id) : ''));
        setError('');
      })
      .catch((caughtError: unknown) => {
        if (active) setError(getErrorMessage(caughtError));
      });

    return () => {
      active = false;
    };
  }, []);

  const selected = applications?.find((application) => String(application.id) === selectedId) ?? null;
  const selectedJobId = selected?.jobOffer.id ?? null;

  useEffect(() => {
    if (selectedJobId === null) {
      return;
    }

    let active = true;
    void api<PersistedJobTimelineEvent[]>(`/jobs/${selectedJobId}/timeline`)
      .then((events) => {
        if (!active) return;
        setTimelineState({ jobOfferId: selectedJobId, events });
        setError('');
      })
      .catch((caughtError: unknown) => {
        if (!active) return;
        setTimelineState({ jobOfferId: selectedJobId, events: [] });
        setError(getErrorMessage(caughtError));
      });

    return () => {
      active = false;
    };
  }, [selectedJobId]);

  const persistedEvents = selectedJobId !== null && timelineState?.jobOfferId === selectedJobId
    ? timelineState.events
    : null;

  const timeline = useMemo(
    () => presentJobTimeline(persistedEvents ?? []),
    [persistedEvents],
  );

  return (
    <>
      <PageHeader
        title="Parcours des candidatures"
        description="Historique métier persistant et en lecture seule de chaque opportunité."
      />

      {error !== '' && <ErrorBox message={error} />}

      <Card>
        {applications === null ? (
          <Loading />
        ) : applications.length === 0 ? (
          <Empty>Aucune candidature n’est disponible pour afficher une chronologie.</Empty>
        ) : (
          <div className="stack">
            <label htmlFor="timeline-application">
              Candidature
              <select
                id="timeline-application"
                value={selectedId}
                onChange={(event) => setSelectedId(event.target.value)}
              >
                {applications.map((application) => (
                  <option key={application.id} value={application.id}>
                    {application.jobOffer.title} — {companyName(application)} — {application.status}
                  </option>
                ))}
              </select>
            </label>

            {selected && (
              <div className="notice">
                <strong>{selected.jobOffer.title}</strong> — {companyName(selected)}
                <div className="actions">
                  <Badge tone="blue">Candidature #{selected.id}</Badge>
                  <Badge>{selected.channel}</Badge>
                  <Badge>{selected.status}</Badge>
                  {persistedEvents !== null && <Badge>{timeline.length} événement(s) persisté(s)</Badge>}
                </div>
              </div>
            )}

            {persistedEvents === null ? (
              <Loading />
            ) : timeline.length === 0 ? (
              <Empty>Aucun événement métier n’a encore été enregistré pour cette offre.</Empty>
            ) : (
              <div>
                {timeline.map((event) => (
                  <div className="list-row" key={event.key}>
                    <div>
                      <div className="actions">
                        <Badge tone={event.tone}>{event.title}</Badge>
                        <span className="muted small">{formatDate(event.occurredAt)}</span>
                      </div>
                      <p className="small">{event.description}</p>
                    </div>
                  </div>
                ))}
              </div>
            )}

            <div className="notice warning">
              Cette vue affiche uniquement les événements métier persistés. Le statut courant reste visible comme contexte, mais ne crée pas artificiellement un événement historique.
            </div>
          </div>
        )}
      </Card>
    </>
  );
}
