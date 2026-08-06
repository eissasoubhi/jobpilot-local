export type TimelineMessageCategory =
  | 'JOB_ALERT'
  | 'RECRUITER_OPPORTUNITY'
  | 'APPLICATION_CONFIRMATION'
  | 'APPLICATION_REPLY'
  | 'INTERVIEW_REQUEST'
  | 'REJECTION'
  | 'INFORMATION_REQUEST'
  | 'UNKNOWN';

export type TimelineApplication = {
  id: number;
  status: string;
  channel: string;
  createdAt: string;
  updatedAt: string;
  submittedAt?: string | null;
  submissionAttemptedAt?: string | null;
  submissionError?: string | null;
};

export type TimelineMessage = {
  id: number;
  application: { id: number; status: string } | null;
  category: TimelineMessageCategory;
  subject: string;
  sender: string;
  receivedAt: string;
  gmailUrl: string | null;
  actionRequired: boolean;
  processed: boolean;
};

export type ApplicationTimelineEvent = {
  key: string;
  occurredAt: string;
  kind: 'CREATED' | 'SUBMISSION_ATTEMPT' | 'SUBMITTED' | 'MESSAGE' | 'CURRENT_STATUS';
  title: string;
  description: string;
  tone: 'good' | 'warn' | 'bad' | 'blue' | 'neutral';
  href?: string | null;
};

const messageLabels: Record<TimelineMessageCategory, string> = {
  JOB_ALERT: 'Alerte emploi reçue',
  RECRUITER_OPPORTUNITY: 'Proposition recruteur reçue',
  APPLICATION_CONFIRMATION: 'Confirmation de candidature reçue',
  APPLICATION_REPLY: 'Réponse à la candidature reçue',
  INTERVIEW_REQUEST: 'Invitation à un entretien reçue',
  REJECTION: 'Refus reçu',
  INFORMATION_REQUEST: 'Demande d’informations reçue',
  UNKNOWN: 'Message associé reçu',
};

function messageTone(category: TimelineMessageCategory): ApplicationTimelineEvent['tone'] {
  if (category === 'INTERVIEW_REQUEST') return 'good';
  if (category === 'REJECTION') return 'bad';
  if (category === 'INFORMATION_REQUEST' || category === 'RECRUITER_OPPORTUNITY') return 'warn';
  return category === 'UNKNOWN' ? 'neutral' : 'blue';
}

function statusTone(status: string): ApplicationTimelineEvent['tone'] {
  if (['INTERVIEW', 'OFFER_RECEIVED', 'SUBMITTED', 'APPLICATION_CONFIRMED'].includes(status)) return 'good';
  if (['REJECTED', 'SUBMISSION_FAILED'].includes(status)) return 'bad';
  if (['INFORMATION_REQUESTED', 'SUBMISSION_PENDING'].includes(status)) return 'warn';
  return 'blue';
}

export function buildApplicationTimeline(
  application: TimelineApplication,
  messages: TimelineMessage[],
): ApplicationTimelineEvent[] {
  const events: ApplicationTimelineEvent[] = [
    {
      key: `application-${application.id}-created`,
      occurredAt: application.createdAt,
      kind: 'CREATED',
      title: 'Candidature préparée',
      description: `Canal initial : ${application.channel}.`,
      tone: 'blue',
    },
  ];

  if (application.submissionAttemptedAt) {
    events.push({
      key: `application-${application.id}-attempt`,
      occurredAt: application.submissionAttemptedAt,
      kind: 'SUBMISSION_ATTEMPT',
      title: application.submissionError ? 'Tentative d’envoi échouée' : 'Tentative d’envoi démarrée',
      description: application.submissionError ?? 'Une tentative d’envoi autorisée a été enregistrée.',
      tone: application.submissionError ? 'bad' : 'warn',
    });
  }

  if (application.submittedAt) {
    events.push({
      key: `application-${application.id}-submitted`,
      occurredAt: application.submittedAt,
      kind: 'SUBMITTED',
      title: 'Candidature envoyée',
      description: `Canal : ${application.channel}.`,
      tone: 'good',
    });
  }

  for (const message of messages) {
    if (message.application?.id !== application.id) continue;

    events.push({
      key: `message-${message.id}`,
      occurredAt: message.receivedAt,
      kind: 'MESSAGE',
      title: messageLabels[message.category],
      description: `${message.subject || '(sans objet)'} — ${message.sender}${message.actionRequired && !message.processed ? ' · action requise' : ''}`,
      tone: messageTone(message.category),
      href: message.gmailUrl,
    });
  }

  events.push({
    key: `application-${application.id}-status`,
    occurredAt: application.updatedAt,
    kind: 'CURRENT_STATUS',
    title: `Statut actuel : ${application.status}`,
    description: 'Dernier état enregistré dans JobPilot. Les anciens changements manuels ne sont pas reconstruits lorsqu’aucun événement source n’existe.',
    tone: statusTone(application.status),
  });

  return events.sort((left, right) => {
    const dateDifference = new Date(right.occurredAt).getTime() - new Date(left.occurredAt).getTime();
    return dateDifference !== 0 ? dateDifference : left.key.localeCompare(right.key);
  });
}
