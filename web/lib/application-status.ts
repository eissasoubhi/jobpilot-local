import type { Application } from '@/lib/types';

export const APPLICATION_STATUSES = [
  'READY_TO_SUBMIT',
  'SUBMITTED',
  'SUBMISSION_PENDING',
  'SUBMISSION_FAILED',
  'MISSING_CV',
  'DRAFT',
  'APPLICATION_CONFIRMED',
  'RESPONSE_RECEIVED',
  'INFORMATION_REQUESTED',
  'RECRUITER_REPLIED',
  'INTERVIEW',
  'REJECTED',
  'OFFER_RECEIVED',
  'OFFER_UNAVAILABLE',
  'IGNORED_NOT_MATCH',
] as const;

export type ApplicationStatusFilter = 'ALL' | string;

const STATUS_LABELS: Readonly<Record<string, string>> = {
  READY_TO_SUBMIT: 'Prêtes à envoyer',
  SUBMITTED: 'Envoyées',
  SUBMISSION_PENDING: 'Envoi en cours',
  SUBMISSION_FAILED: 'Échec de l’envoi',
  MISSING_CV: 'CV manquant',
  DRAFT: 'Brouillons',
  APPLICATION_CONFIRMED: 'Candidatures confirmées',
  RESPONSE_RECEIVED: 'Réponses reçues',
  INFORMATION_REQUESTED: 'Informations demandées',
  RECRUITER_REPLIED: 'Réponses recruteur',
  INTERVIEW: 'Entretiens',
  REJECTED: 'Refusées',
  OFFER_RECEIVED: 'Offres reçues',
  OFFER_UNAVAILABLE: 'Offres indisponibles',
  IGNORED_NOT_MATCH: 'Ne correspondent pas au profil',
};

const BADGE_LABELS: Readonly<Record<string, string>> = {
  READY_TO_SUBMIT: 'PRÊTE À ENVOYER',
  SUBMITTED: 'ENVOYÉE',
  SUBMISSION_PENDING: 'ENVOI EN COURS',
  SUBMISSION_FAILED: 'ÉCHEC DE L’ENVOI',
  MISSING_CV: 'CV MANQUANT',
  DRAFT: 'BROUILLON',
  APPLICATION_CONFIRMED: 'CANDIDATURE CONFIRMÉE',
  RESPONSE_RECEIVED: 'RÉPONSE REÇUE',
  INFORMATION_REQUESTED: 'INFORMATIONS DEMANDÉES',
  RECRUITER_REPLIED: 'RÉPONSE RECRUTEUR',
  INTERVIEW: 'ENTRETIEN',
  REJECTED: 'REFUSÉE',
  OFFER_RECEIVED: 'OFFRE REÇUE',
  OFFER_UNAVAILABLE: 'OFFRE INDISPONIBLE',
  IGNORED_NOT_MATCH: 'NE CORRESPOND PAS AU PROFIL',
};

export type ApplicationStatusTone = 'good' | 'warn' | 'bad' | 'blue' | 'neutral';

export type ApplicationStatusOption = {
  value: ApplicationStatusFilter;
  label: string;
  count: number;
};

export function applicationStatusLabel(status: string): string {
  return STATUS_LABELS[status] ?? humanizeStatus(status);
}

export function applicationBadgeLabel(application: Application): string {
  if (application.status === 'SUBMITTED' && application.channel === 'Gmail automatique') {
    return 'ENVOYÉE AUTOMATIQUEMENT';
  }

  return BADGE_LABELS[application.status] ?? humanizeStatus(application.status).toLocaleUpperCase('fr');
}

export function applicationStatusTone(status: string): ApplicationStatusTone {
  if (status === 'SUBMITTED' || status === 'APPLICATION_CONFIRMED' || status === 'OFFER_RECEIVED') {
    return 'good';
  }
  if (status === 'SUBMISSION_PENDING' || status === 'MISSING_CV' || status === 'INFORMATION_REQUESTED') {
    return 'warn';
  }
  if (status === 'SUBMISSION_FAILED' || status === 'REJECTED') {
    return 'bad';
  }
  if (status === 'DRAFT' || status === 'IGNORED_NOT_MATCH' || status === 'OFFER_UNAVAILABLE') {
    return 'neutral';
  }

  return 'blue';
}

export function filterApplications(
  applications: readonly Application[],
  statusFilter: ApplicationStatusFilter,
): Application[] {
  if (statusFilter === 'ALL') {
    return [...applications];
  }

  return applications.filter((application) => application.status === statusFilter);
}

export function applicationStatusOptions(
  applications: readonly Application[],
): ApplicationStatusOption[] {
  const counts = new Map<string, number>();
  for (const application of applications) {
    counts.set(application.status, (counts.get(application.status) ?? 0) + 1);
  }

  const knownStatuses = new Set<string>(APPLICATION_STATUSES);
  const unknownStatuses = [...counts.keys()]
    .filter((status) => !knownStatuses.has(status))
    .sort((left, right) => applicationStatusLabel(left).localeCompare(applicationStatusLabel(right), 'fr'));

  return [
    { value: 'ALL', label: 'Toutes les candidatures', count: applications.length },
    ...[...APPLICATION_STATUSES, ...unknownStatuses].map((status) => ({
      value: status,
      label: applicationStatusLabel(status),
      count: counts.get(status) ?? 0,
    })),
  ];
}

function humanizeStatus(status: string): string {
  const normalized = status
    .trim()
    .toLocaleLowerCase('fr')
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ');

  if (normalized === '') {
    return 'Statut inconnu';
  }

  return normalized.charAt(0).toLocaleUpperCase('fr') + normalized.slice(1);
}
