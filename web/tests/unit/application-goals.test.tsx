import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ApplicationGoalAlerts } from '@/components/ApplicationGoalAlerts';
import { ApplicationGoalsPanel } from '@/components/ApplicationGoalsPanel';
import { ApplicationGoalsSettings } from '@/components/ApplicationGoalsSettings';
import {
  applicationGoalPaceTone,
  type ApplicationGoalSnapshot,
} from '@/lib/application-goals';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));

const snapshot: ApplicationGoalSnapshot = {
  config: {
    daily: 2,
    weekly: 10,
    monthly: 40,
    timezone: 'Europe/Paris',
    startedAt: '2026-08-01T08:00:00+02:00',
  },
  periods: {
    daily: {
      period: 'daily', label: 'Aujourd’hui', enabled: true, target: 2, achieved: 1,
      remaining: 1, percent: 50, completed: false,
      start: '2026-08-20T00:00:00+02:00', end: '2026-08-21T00:00:00+02:00',
    },
    weekly: {
      period: 'weekly', label: 'Cette semaine', enabled: true, target: 10, achieved: 6,
      remaining: 4, percent: 60, completed: false,
      start: '2026-08-17T00:00:00+02:00', end: '2026-08-24T00:00:00+02:00',
    },
    monthly: {
      period: 'monthly', label: 'Ce mois', enabled: true, target: 40, achieved: 25,
      remaining: 15, percent: 63, completed: false,
      start: '2026-08-01T00:00:00+02:00', end: '2026-09-01T00:00:00+02:00',
    },
  },
  missed: [{
    period: 'daily',
    label: 'Objectif d’hier manqué',
    target: 2,
    achieved: 1,
    remaining: 1,
    start: '2026-08-19T00:00:00+02:00',
    end: '2026-08-20T00:00:00+02:00',
  }],
  generatedAt: '2026-08-20T14:00:00+02:00',
};

describe('application goals', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('keeps Review Queue limited to compact goal progress', async () => {
    apiMock.mockResolvedValueOnce(snapshot);

    render(<ApplicationGoalsPanel />);

    expect(await screen.findByText('1/2 · 50%')).toBeInTheDocument();
    expect(screen.getByText('6/10 · 60%')).toBeInTheDocument();
    expect(screen.getByText('25/40 · 63%')).toBeInTheDocument();
    expect(screen.queryByRole('spinbutton', { name: 'Objectif journalier de candidatures' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Enregistrer/ })).not.toBeInTheDocument();
  });

  it('colors goals from achieved pace versus elapsed time', () => {
    const period = {
      ...snapshot.periods.daily,
      target: 10,
      achieved: 6,
      remaining: 4,
      percent: 60,
    };

    // 60% of the day elapsed and 60% achieved: on pace.
    expect(applicationGoalPaceTone(period, Date.parse('2026-08-20T14:24:00+02:00'))).toBe('normal');

    // Same time, but only 30% achieved: behind the expected pace.
    expect(applicationGoalPaceTone(
      { ...period, achieved: 3, remaining: 7, percent: 30 },
      Date.parse('2026-08-20T14:24:00+02:00'),
    )).toBe('warning');

    // Deadline is very close, but 90% achieved at about 92% elapsed is still on track.
    expect(applicationGoalPaceTone(
      { ...period, achieved: 9, remaining: 1, percent: 90 },
      Date.parse('2026-08-20T22:05:00+02:00'),
    )).toBe('normal');

    // Deadline is very close and the goal is far behind: critical.
    expect(applicationGoalPaceTone(
      { ...period, achieved: 3, remaining: 7, percent: 30 },
      Date.parse('2026-08-20T22:48:00+02:00'),
    )).toBe('critical');

    expect(applicationGoalPaceTone(period, Date.parse('2026-08-21T00:01:00+02:00'))).toBe('critical');
    expect(applicationGoalPaceTone(
      { ...period, completed: true, achieved: 10, remaining: 0, percent: 100 },
      Date.parse('2026-08-20T23:59:00+02:00'),
    )).toBe('completed');
  });

  it('applies the critical pace state to the compact Review Queue progress', async () => {
    vi.spyOn(Date, 'now').mockReturnValue(Date.parse('2026-08-20T22:48:00+02:00'));
    apiMock.mockResolvedValueOnce({
      ...snapshot,
      periods: {
        ...snapshot.periods,
        daily: {
          ...snapshot.periods.daily,
          target: 10,
          achieved: 3,
          remaining: 7,
          percent: 30,
        },
      },
    });

    render(<ApplicationGoalsPanel />);

    const dailyLabel = await screen.findByText('Aujourd’hui');
    expect(dailyLabel.closest('article')).toHaveAttribute('data-pace-tone', 'critical');
  });

  it('configures daily, weekly and monthly targets from settings', async () => {
    apiMock.mockResolvedValueOnce(snapshot).mockResolvedValueOnce({
      ...snapshot,
      config: { ...snapshot.config, daily: 3 },
      periods: { ...snapshot.periods, daily: { ...snapshot.periods.daily, target: 3, remaining: 2, percent: 33 } },
    });

    render(<ApplicationGoalsSettings />);

    const dailyInput = await screen.findByRole('spinbutton', { name: 'Objectif journalier de candidatures' });
    expect(dailyInput).toHaveValue(2);
    expect(screen.getByRole('spinbutton', { name: 'Objectif hebdomadaire de candidatures' })).toHaveValue(10);
    expect(screen.getByRole('spinbutton', { name: 'Objectif mensuel de candidatures' })).toHaveValue(40);

    fireEvent.change(dailyInput, { target: { value: '3' } });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer les objectifs' }));

    await waitFor(() => expect(apiMock).toHaveBeenLastCalledWith('/application-goals', {
      method: 'PUT',
      body: expect.stringContaining('"daily":3'),
    }));
    expect(await screen.findByText('Objectifs enregistrés.')).toBeInTheDocument();
  });

  it('surfaces missed goals and the remaining daily target as in-app alerts', async () => {
    apiMock.mockResolvedValue(snapshot);

    const view = render(<ApplicationGoalAlerts />);

    expect(await screen.findByText('Objectif d’hier manqué')).toBeInTheDocument();
    expect(screen.getByText('Objectif du jour : 1 / 2')).toBeInTheDocument();
    expect(screen.getByText('Il te reste 1 candidature(s) à envoyer aujourd’hui.')).toBeInTheDocument();
    expect(screen.getAllByRole('link', { name: /Review Queue|Continuer/ })[0]).toHaveAttribute('href', '/offres/review');

    view.unmount();
  });
});
