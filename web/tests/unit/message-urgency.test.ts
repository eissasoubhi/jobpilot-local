import { describe, expect, it } from 'vitest';

import {
  filterMessages,
  sortMessagesByUrgency,
  urgencyCounts,
  type UrgencyMessage,
} from '@/lib/message-urgency';

function message(
  id: number,
  level: 'URGENT' | 'PRIORITY' | 'NORMAL',
  category: string,
  receivedAt: string,
  actionRequired = level !== 'NORMAL',
  processed = false,
): UrgencyMessage {
  return {
    id,
    category,
    receivedAt,
    actionRequired,
    processed,
    urgency: {
      level,
      label: level === 'URGENT' ? 'Urgent' : level === 'PRIORITY' ? 'Prioritaire' : 'Normal',
      actionRequired: actionRequired && !processed,
      reasons: [],
      recommendedAction: actionRequired && !processed ? 'Traiter ce message' : null,
      ageHours: 1,
    },
  };
}

describe('message urgency helpers', () => {
  const items = [
    message(1, 'NORMAL', 'JOB_ALERT', '2026-08-14T12:00:00+02:00', false),
    message(2, 'PRIORITY', 'RECRUITER_OPPORTUNITY', '2026-08-14T10:00:00+02:00'),
    message(3, 'URGENT', 'INTERVIEW_REQUEST', '2026-08-14T09:00:00+02:00'),
    message(4, 'URGENT', 'INFORMATION_REQUEST', '2026-08-14T11:00:00+02:00'),
    message(5, 'NORMAL', 'REJECTION', '2026-08-14T13:00:00+02:00', false),
  ];

  it('puts urgent items first while preserving recency inside the same urgency level', () => {
    expect(sortMessagesByUrgency(items).map((item) => item.id)).toEqual([4, 3, 2, 5, 1]);
  });

  it('filters urgent, priority and action-required views without changing the input', () => {
    expect(filterMessages(items, 'URGENT').map((item) => item.id)).toEqual([3, 4]);
    expect(filterMessages(items, 'PRIORITY').map((item) => item.id)).toEqual([2, 3, 4]);
    expect(filterMessages(items, 'ACTION_REQUIRED').map((item) => item.id)).toEqual([2, 3, 4]);
    expect(filterMessages(items, 'RECRUITER_OPPORTUNITY').map((item) => item.id)).toEqual([2]);
    expect(items).toHaveLength(5);
  });

  it('returns stable counts independently of the selected UI filter', () => {
    expect(urgencyCounts(items)).toEqual({
      urgent: 2,
      priority: 3,
      actionRequired: 3,
    });
  });
});
