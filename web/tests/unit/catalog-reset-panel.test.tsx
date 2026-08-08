import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { CatalogResetPanel } from '@/components/CatalogResetPanel';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));

describe('CatalogResetPanel', () => {
  beforeEach(() => {
    apiMock.mockReset();
    vi.restoreAllMocks();
  });

  it('cleans only non-matching offers after a simple confirmation', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    apiMock.mockResolvedValueOnce({
      message: '38 offre(s) hors profil supprimée(s). 91 offre(s) conservée(s).',
      cleanup: {
        busy: false,
        scannedOffers: 129,
        deletedOffers: 38,
        deletedApplications: 31,
        deletedOccurrences: 42,
        deletedMarkedNotMatch: 7,
        deletedStoredAiNoMatch: 25,
        deletedAiNoMatch: 6,
        protectedHistoryOffers: 4,
        keptOffers: 91,
      },
    });

    render(<CatalogResetPanel />);
    fireEvent.click(screen.getByRole('button', { name: 'Nettoyer les offres hors profil' }));

    expect(window.confirm).toHaveBeenCalledWith(expect.stringContaining('uniquement les offres clairement hors profil'));
    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/job-search/cleanup-profile', {
      method: 'POST',
      body: JSON.stringify({ confirmation: 'CLEAN_NO_MATCH' }),
    }));

    const result = await screen.findByTestId('cleanup-result');
    expect(result).toHaveTextContent('129 offres analysées');
    expect(result).toHaveTextContent('38 hors profil supprimées');
    expect(result).toHaveTextContent('4 historiques protégés');
    expect(result).toHaveTextContent('91 offres conservées');
    expect(screen.getByRole('link', { name: 'Voir le catalogue nettoyé →' })).toHaveAttribute('href', '/offres');
  });

  it('keeps the destructive action locked until the explicit phrase is entered', () => {
    render(<CatalogResetPanel />);

    const button = screen.getByRole('button', { name: 'Supprimer et resynchroniser' });
    const input = screen.getByRole('textbox', { name: 'Confirmation de réinitialisation des offres' });

    expect(button).toBeDisabled();
    fireEvent.change(input, { target: { value: 'reset' } });
    expect(button).toBeDisabled();
    fireEvent.change(input, { target: { value: 'REINITIALISER' } });
    expect(button).toBeEnabled();
  });

  it('requires a final confirmation then resets and shows the fresh sync summary', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    apiMock.mockResolvedValueOnce({
      message: 'Catalogue supprimé puis resynchronisé depuis les sources actives.',
      reset: {
        busy: false,
        deletedOffers: 240,
        deletedApplications: 90,
        deletedOccurrences: 265,
      },
      sync: {
        received: 130,
        imported: 82,
        merged: 4,
        profileFiltered: 44,
        failed: 0,
        busy: false,
        skipped: false,
      },
    });

    render(<CatalogResetPanel />);
    fireEvent.change(
      screen.getByRole('textbox', { name: 'Confirmation de réinitialisation des offres' }),
      { target: { value: 'REINITIALISER' } },
    );
    fireEvent.click(screen.getByRole('button', { name: 'Supprimer et resynchroniser' }));

    expect(window.confirm).toHaveBeenCalledWith(expect.stringContaining('candidatures qui leur sont liées'));
    await waitFor(() => expect(apiMock).toHaveBeenCalledWith('/job-search/reset', {
      method: 'POST',
      body: JSON.stringify({ confirmation: 'RESET_OFFERS' }),
    }));

    expect(await screen.findByRole('status')).toHaveTextContent('240 offres supprimées');
    expect(screen.getByRole('status')).toHaveTextContent('90 candidatures supprimées');
    expect(screen.getByRole('status')).toHaveTextContent('82 nouvelles offres');
    expect(screen.getByRole('status')).toHaveTextContent('44 hors profil filtrées');
    expect(screen.getByRole('link', { name: 'Voir le nouveau catalogue →' })).toHaveAttribute('href', '/offres');
  });
});
