export type JobTimelineEventType =
  | 'OFFER_IMPORTED'
  | 'SOURCE_OCCURRENCE_ADDED'
  | 'PREPARATION_CREATED'
  | 'PREPARATION_UPDATED'
  | 'APPLICATION_SUBMITTED'
  | 'RESPONSE_RECEIVED'
  | 'REJECTED'
  | 'INTERVIEW'
  | 'FOLLOW_UP';

export type PersistedJobTimelineEvent = {
  id: number;
  jobOfferId: number;
  applicationId: number | null;
  type: JobTimelineEventType;
  source: string;
  payload: Record<string, unknown>;
  occurredAt: string;
  recordedAt: string;
};

export type ApplicationTimelineEvent = {
  key: string;
  occurredAt: string;
  title: string;
  description: string;
  tone: 'good' | 'warn' | 'bad' | 'blue' | 'neutral';
};

type EventPresentation = Pick<ApplicationTimelineEvent, 'title' | 'description' | 'tone'>;

const eventPresentations: Record<JobTimelineEventType, EventPresentation> = {
  OFFER_IMPORTED: {
    title: 'Offre importée',
    description: 'L’offre a été ajoutée au catalogue JobPilot.',
    tone: 'blue',
  },
  SOURCE_OCCURRENCE_ADDED: {
    title: 'Nouvelle source ajoutée',
    description: 'Une occurrence de la même offre a été rapprochée de cette opportunité.',
    tone: 'blue',
  },
  PREPARATION_CREATED: {
    title: 'Préparation créée',
    description: 'La préparation de la candidature a été créée.',
    tone: 'blue',
  },
  PREPARATION_UPDATED: {
    title: 'Préparation mise à jour',
    description: 'La préparation de la candidature a été modifiée.',
    tone: 'neutral',
  },
  APPLICATION_SUBMITTED: {
    title: 'Candidature envoyée',
    description: 'Le passage au statut envoyé a été enregistré.',
    tone: 'good',
  },
  RESPONSE_RECEIVED: {
    title: 'Réponse reçue',
    description: 'Une réponse associée à la candidature a été reçue.',
    tone: 'blue',
  },
  REJECTED: {
    title: 'Refus reçu',
    description: 'Un refus associé à la candidature a été reçu.',
    tone: 'bad',
  },
  INTERVIEW: {
    title: 'Entretien proposé',
    description: 'Une invitation à un entretien a été reçue.',
    tone: 'good',
  },
  FOLLOW_UP: {
    title: 'Relance effectuée',
    description: 'Une relance de la candidature a été enregistrée.',
    tone: 'warn',
  },
};

export function presentJobTimeline(events: PersistedJobTimelineEvent[]): ApplicationTimelineEvent[] {
  return events
    .map((event) => ({
      key: `timeline-${event.id}`,
      occurredAt: event.occurredAt,
      ...eventPresentations[event.type],
    }))
    .sort((left, right) => {
      const dateDifference = new Date(right.occurredAt).getTime() - new Date(left.occurredAt).getTime();
      return dateDifference !== 0 ? dateDifference : left.key.localeCompare(right.key);
    });
}
