import type { Meta, StoryObj } from '@storybook/nextjs-vite';

import type { ApplicationGoalPeriod, ApplicationGoalSnapshot } from '@/lib/application-goals';

import { ApplicationGoalAlertsSummary } from './ApplicationGoalAlerts';

function period(
  value: Partial<ApplicationGoalPeriod> & Pick<ApplicationGoalPeriod, 'period' | 'label' | 'target' | 'achieved' | 'start' | 'end'>,
): ApplicationGoalPeriod {
  const remaining = Math.max(0, value.target - value.achieved);
  const percent = value.target === 0 ? 0 : Math.round((value.achieved / value.target) * 100);

  return {
    enabled: true,
    remaining,
    percent,
    completed: value.achieved >= value.target,
    ...value,
  };
}

const daily = period({
  period: 'daily',
  label: 'Aujourd’hui',
  target: 3,
  achieved: 1,
  start: '2026-09-13T00:00:00Z',
  end: '2026-09-14T00:00:00Z',
});

const baseSnapshot: ApplicationGoalSnapshot = {
  config: {
    daily: 3,
    weekly: 10,
    monthly: 30,
    timezone: 'Europe/Paris',
    startedAt: '2026-09-01T00:00:00+02:00',
  },
  periods: {
    daily,
    weekly: period({
      period: 'weekly',
      label: 'Cette semaine',
      target: 10,
      achieved: 8,
      start: '2026-09-07T00:00:00Z',
      end: '2026-09-14T00:00:00Z',
    }),
    monthly: period({
      period: 'monthly',
      label: 'Ce mois',
      target: 30,
      achieved: 20,
      start: '2026-09-01T00:00:00Z',
      end: '2026-10-01T00:00:00Z',
    }),
  },
  missed: [],
  generatedAt: '2026-09-13T12:00:00Z',
};

const missedWeekly: ApplicationGoalSnapshot = {
  ...baseSnapshot,
  periods: {
    ...baseSnapshot.periods,
    daily: { ...daily, completed: true, achieved: 3, remaining: 0, percent: 100 },
  },
  missed: [
    {
      period: 'weekly',
      label: 'Semaine précédente',
      target: 10,
      achieved: 7,
      remaining: 3,
      start: '2026-08-31T00:00:00Z',
      end: '2026-09-07T00:00:00Z',
    },
  ],
};

const meta = {
  title: 'Applications/Application goal alerts',
  component: ApplicationGoalAlertsSummary,
  parameters: {
    layout: 'centered',
  },
  args: {
    snapshot: baseSnapshot,
  },
} satisfies Meta<typeof ApplicationGoalAlertsSummary>;

export default meta;
type Story = StoryObj<typeof meta>;

export const DailyReminder: Story = {};

export const MissedGoal: Story = {
  args: {
    snapshot: missedWeekly,
  },
};

export const MissedGoalAndDailyReminder: Story = {
  args: {
    snapshot: {
      ...baseSnapshot,
      missed: missedWeekly.missed,
    },
  },
};
