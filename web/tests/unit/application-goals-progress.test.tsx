import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ApplicationGoalsPanel } from '@/components/ApplicationGoalsPanel';
import type { ApplicationGoalSnapshot } from '@/lib/application-goals';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));

const snapshot: ApplicationGoalSnapshot = {
  config: {
    daily: 2,
    weekly: 0,
    monthly: 0,
    timezone: 'Europe/Paris',
    startedAt: '2026-08-01T08:00:00+02:00',
  },
  periods: {
    daily: {
      period: 'daily',
      label: 'Aujourd’hui',
      enabled: true,
      target: 2,
      achieved: 1,
      remaining: 1,
      percent: 50,
      completed: false,
      start: '2026-08-27T00:00:00+02:00',
      end: '2026-08-28T00:00:00+02:00',
    },
    weekly: {
      period: 'weekly', label: 'Cette semaine', enabled: false, target: 0, achieved: 0,
      remaining: 0, percent: 0, completed: false,
      start: '2026-08-24T00:00:00+02:00', end: '2026-08-31T00:00:00+02:00',
    },
    monthly: {
      period: 'monthly', label: 'Ce mois', enabled: false, target: 0, achieved: 0,
      remaining: 0, percent: 0, completed: false,
      start: '2026-08-01T00:00:00+02:00', end: '2026-09-01T00:00:00+02:00',
    },
  },
  missed: [],
  generatedAt: '2026-08-27T12:00:00+02:00',
};

describe('ApplicationGoalsPanel progress', () => {
  beforeEach(() => {
    apiMock.mockReset();
    apiMock.mockResolvedValue(snapshot);
  });

  it('uses the shared accessible skeleton contract while goals are loading', () => {
    apiMock.mockReturnValue(new Promise(() => undefined));

    render(<ApplicationGoalsPanel />);

    const loading = screen.getByRole('status', { name: 'Chargement des objectifs de candidatures' });
    expect(loading).toHaveAttribute('aria-busy', 'true');
    expect(loading.querySelectorAll('[aria-hidden="true"]')).toHaveLength(6);
  });

  it('exposes compact goal progress through the shared accessible progress bar', async () => {
    render(<ApplicationGoalsPanel />);

    const progress = await screen.findByRole('progressbar', {
      name: 'Progression de l’objectif Aujourd’hui',
    });

    expect(progress).toHaveAttribute('aria-valuenow', '50');
    expect(progress).toHaveAttribute('aria-valuetext', '1 sur 2 · 50%');
    expect(screen.getByText('1/2 · 50%')).toBeInTheDocument();
  });
});
