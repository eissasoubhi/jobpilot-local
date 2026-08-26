export type TransactionActionCategory = 'INTERVIEW_REQUEST' | 'INFORMATION_REQUEST';

export type TransactionActionCopy = {
  completeLabel: string;
  reopenLabel: string;
  completedBadge: string;
  help: string;
};

const COPY: Record<TransactionActionCategory, TransactionActionCopy> = {
  INTERVIEW_REQUEST: {
    completeLabel: 'Marquer l’entretien comme confirmé',
    reopenLabel: 'Remettre l’entretien à traiter',
    completedBadge: 'Entretien confirmé',
    help: 'Met à jour uniquement JobPilot après confirmation du créneau. Aucun message n’est envoyé automatiquement.',
  },
  INFORMATION_REQUEST: {
    completeLabel: 'Marquer les informations comme envoyées',
    reopenLabel: 'Remettre la demande à traiter',
    completedBadge: 'Informations envoyées',
    help: 'Met à jour uniquement JobPilot après l’envoi demandé. Aucun document ni message n’est envoyé automatiquement.',
  },
};

export function transactionActionCopy(category: string): TransactionActionCopy | null {
  return category === 'INTERVIEW_REQUEST' || category === 'INFORMATION_REQUEST'
    ? COPY[category]
    : null;
}
