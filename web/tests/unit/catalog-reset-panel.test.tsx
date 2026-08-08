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
