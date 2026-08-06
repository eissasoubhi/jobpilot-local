export type CrmFollowUpStatus = 'open' | 'completed' | 'all';

export type CrmFollowUpTask = {
  id: number;
  organizationKey: string;
  contactKey?: string | null;
  title: string;
  note?: string | null;
  dueAt: string;
  completed: boolean;
  completedAt?: string | null;
  createdAt: string;
  updatedAt: string;
};

export function followUpDueLabel(task: CrmFollowUpTask, today = new Date()): 'OVERDUE' | 'TODAY' | 'UPCOMING' | 'COMPLETED' {
  if (task.completed) return 'COMPLETED';

  const due = new Date(`${task.dueAt}T12:00:00`);
  const current = new Date(today.getFullYear(), today.getMonth(), today.getDate(), 12, 0, 0);
  const dueDay = new Date(due.getFullYear(), due.getMonth(), due.getDate(), 12, 0, 0);

  if (dueDay.getTime() < current.getTime()) return 'OVERDUE';
  if (dueDay.getTime() === current.getTime()) return 'TODAY';
  return 'UPCOMING';
}

export function formatFollowUpDate(value: string): string {
  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(new Date(`${value}T12:00:00`));
}
