export type MessageUrgencyLevel = 'URGENT' | 'PRIORITY' | 'NORMAL';

export type MessageUrgency = {
  level: MessageUrgencyLevel;
  label: string;
  actionRequired: boolean;
  reasons: string[];
  recommendedAction: string | null;
  ageHours: number;
};

export type UrgencyMessage = {
  id: number;
  category: string;
  receivedAt: string;
  actionRequired: boolean;
  processed: boolean;
  urgency: MessageUrgency;
};

export type MessageFilter = 'ALL' | 'URGENT' | 'PRIORITY' | 'ACTION_REQUIRED' | string;

const urgencyRank: Record<MessageUrgencyLevel, number> = {
  URGENT: 3,
  PRIORITY: 2,
  NORMAL: 1,
};

export function filterMessages<T extends UrgencyMessage>(items: readonly T[], filter: MessageFilter): T[] {
  if (filter === 'ALL') return [...items];
  if (filter === 'URGENT') return items.filter((item) => item.urgency.level === 'URGENT');
  if (filter === 'PRIORITY') {
    return items.filter((item) => item.urgency.level === 'URGENT' || item.urgency.level === 'PRIORITY');
  }
  if (filter === 'ACTION_REQUIRED') {
    return items.filter((item) => item.actionRequired && !item.processed);
  }

  return items.filter((item) => item.category === filter);
}

export function sortMessagesByUrgency<T extends UrgencyMessage>(items: readonly T[]): T[] {
  return [...items].sort((left, right) => {
    const urgencyDifference = urgencyRank[right.urgency.level] - urgencyRank[left.urgency.level];
    if (urgencyDifference !== 0) return urgencyDifference;

    const actionDifference = Number(right.urgency.actionRequired) - Number(left.urgency.actionRequired);
    if (actionDifference !== 0) return actionDifference;

    return new Date(right.receivedAt).getTime() - new Date(left.receivedAt).getTime();
  });
}

export function urgencyCounts(items: readonly UrgencyMessage[]): {
  urgent: number;
  priority: number;
  actionRequired: number;
} {
  return {
    urgent: items.filter((item) => item.urgency.level === 'URGENT').length,
    priority: items.filter((item) => item.urgency.level === 'URGENT' || item.urgency.level === 'PRIORITY').length,
    actionRequired: items.filter((item) => item.actionRequired && !item.processed).length,
  };
}
