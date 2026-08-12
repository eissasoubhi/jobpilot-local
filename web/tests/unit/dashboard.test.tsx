import { render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import DashboardPage from '@/app/page';

const dashboardPayload = {
  period: { days: 7, from: '2026-08-05', to: '2026-08-11' },
  comparison: {
    newJobs: { current: 8, previous: 6, deltaPercent: 33.3 },
    submitted: { current: 4, previous: 2, deltaPercent: 100 },
  },
  counts: {
    jobs: 24,
    newJobs: 8,
    qualifiedJobs: 14,
    applications: 10,
    prepared: 3,
    submitted: 7,
    submittedRecently: 4,
    interviews: 2,
    rejected: 1,
    positionings: 1,
    messages: 5,
    actionMessages: 2,
    followUpsDue: 1,
    missingCv: 0,
    failedSubmissions: 0,
  },
  performance: {
    responseRate: 57.1,
    interviewRate: 28.6,
    responses: 4,
    averageScore: 86,
  },
  pipeline: [
    { key: 'detected', label: 'Offres détectées', value: 24 },
    { key: 'qualified', label: 'Score ≥ 85', value: 14 },
    { key: 'prepared', label: 'Candidatures préparées', value: 10 },
    { key: 'submitted', label: 'Envoyées', value: 7 },
    { key: 'interviews', label: 'Entretiens', value: 2 },
  ],
  trend: [
    { date: '2026-08-05', jobs: 1, submitted: 0 },
    { date: '2026-08-06', jobs: 2, submitted: 1 },
    { date: '2026-08-07', jobs: 0, submitted: 0 },
    { date: '2026-08-08', jobs: 1, submitted: 1 },
    { date: '2026-08-09', jobs: 2, submitted: 0 },
    { date: '2026-08-10', jobs: 1, submitted: 1 },
    { date: '2026-08-11', jobs: 1, submitted: 1 },
  ],
  automation: {
    matchingThreshold: 85,
    autoPrepare: true,
    autoSubmitEnabled: false,
    autoSubmitThreshold: 90,
    autoSubmitDailyLimit: 5,
    targetJobsCount: 7,
  },
  connectors: {
    total: 6,
    operational: 5,
    needsAttention: 1,
    lastSyncedAt: '2026-08-11T17:45:00+02:00',
  },
  recentJobs: [],
};

describe('DashboardPage', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('renders a smart daily focus with useful KPIs and priorities', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify(dashboardPayload), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    })));

    render(<DashboardPage />);

    await waitFor(() => expect(screen.getByText('Nouvelles offres')).toBeInTheDocument());
    expect(screen.getByText('Focus du jour')).toBeInTheDocument();
    expect(screen.getByText('À faire maintenant')).toBeInTheDocument();
    expect(screen.getByText('Activité récente')).toBeInTheDocument();
    expect(screen.getByText('Parcours global')).toBeInTheDocument();
    expect(screen.getByText('Performance')).toBeInTheDocument();
    expect(screen.getByText('Actions & configuration')).toBeInTheDocument();
    expect(screen.getByText('Offres à revoir')).toBeInTheDocument();
    expect(screen.getAllByText('Messages à traiter').length).toBeGreaterThanOrEqual(2);
    expect(screen.getByText('Relances dues')).toBeInTheDocument();
    expect(screen.getByText('Connecteurs à vérifier')).toBeInTheDocument();
    expect(screen.getAllByText('57,1 %').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('+33,3 % vs 7 j précédents')).toBeInTheDocument();
    expect(screen.getByText('+100 % vs 7 j précédents')).toBeInTheDocument();
    expect(screen.getByText(/Priorité recommandée :/)).toHaveTextContent('Messages à traiter');
  });

  it('uses the most urgent pending action as the primary dashboard shortcut', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify(dashboardPayload), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    })));

    render(<DashboardPage />);

    await waitFor(() => expect(screen.getByText('Focus du jour')).toBeInTheDocument());
    expect(screen.getAllByRole('link', { name: /Traiter les messages/i }).some((link) => link.getAttribute('href') === '/messages')).toBe(true);
  });

  it('provides direct access to the most relevant work and configuration pages', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify(dashboardPayload), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    })));

    render(<DashboardPage />);

    await waitFor(() => expect(screen.getByText('Actions & configuration')).toBeInTheDocument());
    expect(screen.getAllByRole('link', { name: /Review Queue/i }).length).toBeGreaterThan(0);
    expect(screen.getByRole('link', { name: /Critères de recherche/i })).toHaveAttribute('href', '/criteres-recherche');
    expect(screen.getByRole('link', { name: /Clés API & IA/i })).toHaveAttribute('href', '/parametres/integrations');
    expect(screen.getByRole('link', { name: /Scraping/i })).toHaveAttribute('href', '/parametres/scraping');
    expect(screen.getAllByRole('link', { name: /Reporting/i }).some((link) => link.getAttribute('href') === '/reporting')).toBe(true);
  });
});
