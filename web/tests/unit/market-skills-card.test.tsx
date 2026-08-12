import { render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { MarketSkillsCard } from '@/components/dashboard/MarketSkillsCard';

const marketSkillsPayload = {
  periodDays: 30,
  matchingThreshold: 85,
  analyzedJobs: 8,
  configuredSkillsCount: 12,
  demanded: [
    { label: 'Symfony', count: 6, coveragePercent: 75 },
    { label: 'Docker', count: 5, coveragePercent: 62.5 },
    { label: 'Kubernetes', count: 3, coveragePercent: 37.5 },
  ],
  matching: [
    { label: 'Symfony', count: 6, coveragePercent: 75 },
    { label: 'Docker', count: 5, coveragePercent: 62.5 },
  ],
  unconfigured: [
    { label: 'Kubernetes', count: 3, coveragePercent: 37.5 },
    { label: 'TypeScript', count: 2, coveragePercent: 25 },
  ],
};

describe('MarketSkillsCard', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('separates market demand from skills configured in the profile', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify(marketSkillsPayload), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    })));

    render(<MarketSkillsCard />);

    await waitFor(() => expect(screen.getByText('Compétences récurrentes')).toBeInTheDocument());
    await waitFor(() => expect(screen.getByText(/Basé sur 8 offre\(s\)/)).toBeInTheDocument());

    const unconfiguredSection = screen.getByRole('heading', { name: 'Non configurées dans le profil' }).closest('section');
    expect(unconfiguredSection).not.toBeNull();
    expect(within(unconfiguredSection as HTMLElement).getByText('Kubernetes')).toBeInTheDocument();
    expect(within(unconfiguredSection as HTMLElement).getByText('TypeScript')).toBeInTheDocument();
    expect(screen.getByText(/cela ne signifie pas que tu ne la maîtrises pas/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Profil & compétences/i })).toHaveAttribute('href', '/profil');
  });

  it('keeps a secondary analytics failure from breaking the dashboard card', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ unexpected: true }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    })));

    render(<MarketSkillsCard />);

    await waitFor(() => expect(screen.getByText(/ne sont pas disponibles pour le moment/)).toBeInTheDocument());
  });
});
