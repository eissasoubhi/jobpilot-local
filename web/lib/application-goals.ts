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

export function enabledApplicationGoalPeriods(snapshot: ApplicationGoalSnapshot): ApplicationGoalPeriod[] {
  return [snapshot.periods.daily, snapshot.periods.weekly, snapshot.periods.monthly]
    .filter((period) => period.enabled);
}

export function applicationGoalProgressWidth(period: ApplicationGoalPeriod): number {
  return Math.max(0, Math.min(100, period.percent));
}
