'use client';

import { useEffect, useMemo, useState } from 'react';

import { Badge, Card, Empty, ErrorBox, Loading, PageHeader } from '@/components/UI';
import { api } from '@/lib/api';
import {
  buildApplicationTimeline,
  type TimelineApplication,
  type TimelineMessage,
} from '@/lib/application-timeline';
import { getErrorMessage } from '@/lib/errors';
import type { Application } from '@/lib/types';

type ApplicationWithDates = Application & TimelineApplication;

type MessageResponse = TimelineMessage & {
  jobOffer: { id: number; title: string; company: string } | null;
};

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
  const [applications, setApplications] = useState<ApplicationWithDates[] | null>(null);
  const [messages, setMessages] = useState<MessageResponse[] | null>(null);
  const [selectedId, setSelectedId] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;

    void Promise.all([
      api<ApplicationWithDates[]>('/applications'),
      api<MessageResponse[]>('/integrations/gmail/messages'),
    ])
      .then(([loadedApplications, loadedMessages]) => {
        if (!active) return;
        setApplications(loadedApplications);
        setMessages(loadedMessages);
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
  const timeline = useMemo(
    () => selected && messages ? buildApplicationTimeline(selected, messages) : [],
    [messages, selected],
  );

  return (
    <>
      <PageHeader
        title="Parcours des candidatures"
        description="Chronologie locale et en lecture seule des candidatures, envois et messages Gmail déjà associés."
      />

      {error !== '' && <ErrorBox message={error} />}

      <Card>
        {applications === null || messages === null ? (
          <Loading />
        ) : applications.length === 0 ? (
          <Empty>Aucune candidature n’est disponible pour construire une chronologie.</Empty>
        ) : (
          <>
            <label htmlFor="timeline-application" style={{ maxWidth: 620 }}>
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
              <div className="notice" style={{ marginTop: 16 }}>
                <strong>{selected.jobOffer.title}</strong> — {companyName(selected)}
                <div className="actions" style={{ marginTop: 8 }}>
                  <Badge tone="blue">Candidature #{selected.id}</Badge>
                  <Badge>{selected.channel}</Badge>
                  <Badge>{timeline.length} événement(s)</Badge>
                </div>
              </div>
            )}

            <div style={{ marginTop: 18 }}>
              {timeline.map((event) => (
                <div className="list-row" key={event.key}>
                  <div style={{ flex: 1 }}>
                    <div className="actions" style={{ marginBottom: 6 }}>
                      <Badge tone={event.tone}>{event.title}</Badge>
                      <span className="muted small">{formatDate(event.occurredAt)}</span>
                    </div>
                    <div className="small">{event.description}</div>
                    {event.href && (
                      <div style={{ marginTop: 10 }}>
                        <a className="btn secondary small" href={event.href} target="_blank" rel="noreferrer">
                          Ouvrir dans Gmail
                        </a>
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>

            <div className="notice warning" style={{ marginTop: 16 }}>
              Cette vue ne fabrique aucun historique. Elle affiche uniquement les dates stockées sur la candidature et les messages Gmail déjà associés. Un ancien changement manuel sans événement source reste visible seulement comme statut actuel.
            </div>
          </>
        )}
      </Card>
    </>
  );
}
