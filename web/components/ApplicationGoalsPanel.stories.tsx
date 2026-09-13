import type { Meta, StoryObj } from '@storybook/nextjs-vite';

import type { ApplicationGoalPeriod, ApplicationGoalSnapshot } from '@/lib/application-goals';

import { ApplicationGoalsSummary } from './ApplicationGoalsPanel';

const NOW = Date.parse('2026-09-13T12:00:00Z');

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

const mixedSnapshot: ApplicationGoalSnapshot = {
  config: {
    daily: 3,
    weekly: 10,
    monthly: 30,
    timezone: 'Europe/Paris',
    startedAt: '2026-09-01T00:00:00+02:00',
  },
  periods: {
    daily: period({
      period: 'daily',
      label: 'Aujourd’hui',
      target: 3,
      achieved: 3,
      start: '2026-09-13T00:00:00Z',
      end: '2026-09-14T00:00:00Z',
    }),
    weekly: period({
      period: 'weekly',
      label: 'Cette semaine',
      target: 10,
      achieved: 4,
      start: '2026-09-07T00:00:00Z',
      end: '2026-09-14T00:00:00Z',
    }),
    monthly: period({
      period: 'monthly',
      label: 'Ce mois',
      target: 30,
      achieved: 14,
      start: '2026-09-01T00:00:00Z',
      end: '2026-10-01T00:00:00Z',
    }),
  },
  missed: [],
  generatedAt: '2026-09-13T12:00:00Z',
};

const noActiveGoals: ApplicationGoalSnapshot = {
  ...mixedSnapshot,
  config: {
    ...mixedSnapshot.config,
    daily: 0,
    weekly: 0,
    monthly: 0,
  },
  periods: {
    daily: { ...mixedSnapshot.periods.daily, enabled: false, target: 0, achieved: 0, remaining: 0, percent: 0, completed: false },
    weekly: { ...mixedSnapshot.periods.weekly, enabled: false, target: 0, achieved: 0, remaining: 0, percent: 0, completed: false },
    monthly: { ...mixedSnapshot.periods.monthly, enabled: false, target: 0, achieved: 0, remaining: 0, percent: 0, completed: false },
  },
};

const meta = {
  title: 'Applications/Application goals',
  component: ApplicationGoalsSummary,
  parameters: {
    layout: 'centered',
  },
  args: {
    snapshot: mixedSnapshot,
    now: NOW,
  },
} satisfies Meta<typeof ApplicationGoalsSummary>;

export default meta;
type Story = StoryObj<typeof meta>;

export const MixedPace: Story = {};

export const Loading: Story = {
  args: {
    snapshot: null,
  },
};

export const NoActiveGoals: Story = {
  args: {
    snapshot: noActiveGoals,
  },
};

export const ErrorState: Story = {
  args: {
    snapshot: null,
    error: 'Impossible de charger les objectifs. Réessaie après avoir vérifié le serveur local.',
  },
};
