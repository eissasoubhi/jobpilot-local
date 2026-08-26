import { describe, expect, it } from 'vitest';

import { transactionActionCopy } from '@/lib/message-action-completion';

describe('transactionActionCopy', () => {
  it('uses explicit local completion semantics for interview requests', () => {
    const copy = transactionActionCopy('INTERVIEW_REQUEST');

    expect(copy).not.toBeNull();
    expect(copy?.completeLabel).toBe('Marquer l’entretien comme confirmé');
    expect(copy?.reopenLabel).toBe('Remettre l’entretien à traiter');
    expect(copy?.completedBadge).toBe('Entretien confirmé');
    expect(copy?.help).toContain('Aucun message n’est envoyé automatiquement');
  });

  it('uses explicit local completion semantics for information requests', () => {
    const copy = transactionActionCopy('INFORMATION_REQUEST');

    expect(copy).not.toBeNull();
    expect(copy?.completeLabel).toBe('Marquer les informations comme envoyées');
    expect(copy?.reopenLabel).toBe('Remettre la demande à traiter');
    expect(copy?.completedBadge).toBe('Informations envoyées');
    expect(copy?.help).toContain('Aucun document ni message n’est envoyé automatiquement');
  });

  it('keeps generic messages on the existing handled workflow', () => {
    expect(transactionActionCopy('RECRUITER_OPPORTUNITY')).toBeNull();
    expect(transactionActionCopy('MARKETING')).toBeNull();
  });
});
