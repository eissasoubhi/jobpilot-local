import { render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import DashboardPage from '@/app/page';

describe('DashboardPage', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('loads and renders dashboard data without a React effect cleanup error', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({
      counts: { jobs: 2, prepared: 1, submitted: 0, positionings: 1 },
      recentJobs: [],
    }), { status: 200, headers: { 'Content-Type': 'application/json' } })));

    render(<DashboardPage />);

    await waitFor(() => expect(screen.getByText('Offres détectées')).toBeInTheDocument());
    expect(screen.getByText('Prêtes à envoyer')).toBeInTheDocument();
    expect(screen.getByText('Aucune offre importée.')).toBeInTheDocument();
  });
});
