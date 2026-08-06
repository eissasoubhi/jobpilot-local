import { describe, expect, it } from 'vitest';

import { followUpDueLabel, type CrmFollowUpTask } from '@/lib/crm-follow-ups';

const task = (dueAt: string, completed = false): CrmFollowUpTask => ({
  id: 1,
  organizationKey: 'acme',
  contactKey: null,
  title: 'Relancer',
  note: null,
  dueAt,
  completed,
  completedAt: completed ? '2026-08-06T09:00:00+02:00' : null,
  createdAt: '2026-08-01T09:00:00+02:00',
  updatedAt: '2026-08-01T09:00:00+02:00',
});

describe('followUpDueLabel', () => {
  const today = new Date('2026-08-06T09:00:00+02:00');

  it('classifies open tasks by calendar day', () => {
    expect(followUpDueLabel(task('2026-08-05'), today)).toBe('OVERDUE');
    expect(followUpDueLabel(task('2026-08-06'), today)).toBe('TODAY');
    expect(followUpDueLabel(task('2026-08-07'), today)).toBe('UPCOMING');
  });

  it('keeps completed tasks completed regardless of date', () => {
    expect(followUpDueLabel(task('2026-08-01', true), today)).toBe('COMPLETED');
  });
});
