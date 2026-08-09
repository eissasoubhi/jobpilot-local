import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ProfileCleanupPanel } from '@/components/ProfileCleanupPanel';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));

describe('ProfileCleanupPanel', () => {
  beforeEach(() => {
    apiMock.mockReset();
    vi.restoreAllMocks();
  });

  it('does nothing when the confirmation dialog is cancelled', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    render(<ProfileCleanupPanel />);
    fireEvent.click(screen.getByRole('button', { name: 'Nettoyer les offres hors profil' }));

    expect(apiMock).not.toHaveBeenCalled();
  });

  it('states that cleanup reuses stored decisions without new Gemini calls', () => {
    render(<ProfileCleanupPanel />);

    expect(screen.getByText(/ne lance aucune nouvelle analyse Gemini/i)).toBeInTheDocument();
    expect(screen.getByText(/pas de consommation RPM\/TPM\/RPD supplémentaire/i)).toBeInTheDocument();
  });

  it('cleans only confirmed stored mismatches and shows the summary', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    apiMock.mockResolvedValueOnce({
      message: '29 offre(s) hors profil supprimée(s). 120 offre(s) conservée(s).',
      cleanup: {
        busy: false,
        scanned: 149,
        deletedOffers: 29,
        deletedApplications: 24,
        deletedOccurrences: 33,
        manuallyRejected: 8,
        reusedStoredAi: 21,
        aiChecks: 0,
        protectedHistory: 6,
        kept: 120,
      },
    });

    render(<ProfileCleanupPanel />);
    fireEvent.click(screen.getByRole('button', { name: 'Nettoyer les offres hors profil' }));

    expect(window.confirm).toHaveBeenCalledWith(expect.stringContaining('Aucun nouvel appel Gemini'));
    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/job-search/cleanup-profile-mismatches', {
      method: 'POST',
      body: JSON.stringify({ confirmation: 'CLEAN_PROFILE_MISMATCHES' }),
    }));

    const status = await screen.findByRole('status');
    expect(status).toHaveTextContent('149 offres examinées');
    expect(status).toHaveTextContent('29 offres supprimées');
    expect(status).toHaveTextContent('8 rejetées manuellement');
    expect(status).toHaveTextContent('21 décisions IA réutilisées');
    expect(status).toHaveTextContent('6 historiques protégés');
    expect(status).toHaveTextContent('120 conservées');
  });
});
