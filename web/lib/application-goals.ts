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

export type ApplicationGoalPaceTone = 'normal' | 'warning' | 'critical' | 'completed';

const PACE_TOLERANCE = 0.10;
const CRITICAL_LAG = 0.25;
const CRITICAL_REMAINING = 0.10;
const NEAR_DEADLINE_REMAINING = 0.25;

export function enabledApplicationGoalPeriods(snapshot: ApplicationGoalSnapshot): ApplicationGoalPeriod[] {
  return [snapshot.periods.daily, snapshot.periods.weekly, snapshot.periods.monthly]
    .filter((period) => period.enabled);
}

export function applicationGoalProgressWidth(period: ApplicationGoalPeriod): number {
  return Math.max(0, Math.min(100, period.percent));
}

export function applicationGoalPaceTone(
  period: ApplicationGoalPeriod,
  now: number = Date.now(),
): ApplicationGoalPaceTone {
  if (period.completed) return 'completed';

  const start = Date.parse(period.start);
  const end = Date.parse(period.end);

  if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start || period.target <= 0) {
    return 'normal';
  }

  if (now >= end) return 'critical';
  if (now <= start) return 'normal';

  const elapsedRatio = Math.max(0, Math.min(1, (now - start) / (end - start)));
  const progressRatio = Math.max(0, Math.min(1, period.achieved / period.target));
  const paceLag = elapsedRatio - progressRatio;

  // A small tolerance avoids turning an almost-on-track goal orange because
  // applications are discrete while elapsed time is continuous.
  if (paceLag <= PACE_TOLERANCE) return 'normal';

  const remainingRatio = 1 - elapsedRatio;
  const severelyBehindNearDeadline = remainingRatio <= NEAR_DEADLINE_REMAINING
    && paceLag >= CRITICAL_LAG;
  const behindAtFinalStretch = remainingRatio <= CRITICAL_REMAINING;

  if (severelyBehindNearDeadline || behindAtFinalStretch) return 'critical';

  return 'warning';
}
