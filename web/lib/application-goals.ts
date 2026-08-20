export type ApplicationGoalConfig = {
  daily: number;
  weekly: number;
  monthly: number;
  timezone: string;
  startedAt?: string | null;
};

export type ApplicationGoalPeriod = {
  period: 'daily' | 'weekly' | 'monthly';
  label: string;
  enabled: boolean;
  target: number;
  achieved: number;
  remaining: number;
  percent: number;
  completed: boolean;
  start: string;
  end: string;
};

export type MissedApplicationGoal = {
  period: 'daily' | 'weekly' | 'monthly';
  label: string;
  target: number;
  achieved: number;
  remaining: number;
  start: string;
  end: string;
};

export type ApplicationGoalSnapshot = {
  config: ApplicationGoalConfig;
  periods: {
    daily: ApplicationGoalPeriod;
    weekly: ApplicationGoalPeriod;
    monthly: ApplicationGoalPeriod;
  };
  missed: MissedApplicationGoal[];
  generatedAt: string;
};

export type ApplicationGoalDeadlineTone = 'normal' | 'warning' | 'critical' | 'completed';

export function enabledApplicationGoalPeriods(snapshot: ApplicationGoalSnapshot): ApplicationGoalPeriod[] {
  return [snapshot.periods.daily, snapshot.periods.weekly, snapshot.periods.monthly]
    .filter((period) => period.enabled);
}

export function applicationGoalProgressWidth(period: ApplicationGoalPeriod): number {
  return Math.max(0, Math.min(100, period.percent));
}

export function applicationGoalDeadlineTone(
  period: ApplicationGoalPeriod,
  now: number = Date.now(),
): ApplicationGoalDeadlineTone {
  if (period.completed) return 'completed';

  const start = Date.parse(period.start);
  const end = Date.parse(period.end);

  if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) {
    return 'normal';
  }

  if (now >= end) return 'critical';
  if (now <= start) return 'normal';

  const remainingRatio = (end - now) / (end - start);

  if (remainingRatio <= 0.10) return 'critical';
  if (remainingRatio <= 0.25) return 'warning';

  return 'normal';
}
