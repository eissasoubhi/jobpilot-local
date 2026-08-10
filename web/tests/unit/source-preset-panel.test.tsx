import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import SourcePresetPanel from '@/app/parametres/scraping/SourcePresetPanel';

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: apiMock }));

const presets = [
  {
    slug: 'apec-php-symfony',
    name: 'APEC — PHP / Symfony',
    listingUrl: 'https://www.apec.fr/candidat/recherche-emploi.html/emploi?motsCles=PHP%20Symfony',
    mode: 'BROWSER',
    complianceStatus: 'AUTHORIZATION_REQUIRED',
    complianceLabel: 'Autorisation explicite requise',
    canPrefill: true,
    reason: 'Accord préalable requis.',
    recommendedAction: 'Obtenir un accord Apec.',
    termsUrl: 'https://corporate.apec.fr/legal',
    reviewedAt: '2026-08-10',
    syncIntervalMinutes: 360,
    maxPages: 3,
    maxDetails: 15,
  },
  {
    slug: 'welcome-to-the-jungle',
    name: 'Welcome to the Jungle',
    listingUrl: 'https://www.welcometothejungle.com/fr/jobs?query=php',
    mode: 'BROWSER',
    complianceStatus: 'ASSISTED_ONLY',
    complianceLabel: 'Import assisté uniquement',
    canPrefill: false,
    reason: 'Scraping automatisé interdit par les CGU.',
    recommendedAction: 'Utiliser Gmail ou une extension.',
    termsUrl: 'https://www.welcometothejungle.com/fr/pages/terms',
    reviewedAt: '2026-08-10',
    syncIntervalMinutes: 360,
    maxPages: 1,
    maxDetails: 0,
  },
];

describe('SourcePresetPanel', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('keeps assisted-only sites blocked and adds authorized presets disabled', async () => {
    apiMock.mockResolvedValueOnce(presets);
    apiMock.mockResolvedValueOnce({ id: 88, name: 'APEC — PHP / Symfony' });

    render(<SourcePresetPanel />);

    await waitFor(() => expect(screen.getByText('APEC — PHP / Symfony')).toBeInTheDocument());

    const assistedCard = screen.getByText('Welcome to the Jungle').closest('.notice');
    expect(assistedCard).not.toBeNull();
    expect(within(assistedCard as HTMLElement).getByText('Import assisté uniquement')).toBeInTheDocument();
    expect(within(assistedCard as HTMLElement).queryByRole('button', { name: 'Ajouter désactivée' })).not.toBeInTheDocument();

    const apecCard = screen.getByText('APEC — PHP / Symfony').closest('.notice');
    expect(apecCard).not.toBeNull();
    const apec = within(apecCard as HTMLElement);
    const addButton = apec.getByRole('button', { name: 'Ajouter désactivée' });
    expect(addButton).toBeDisabled();

    fireEvent.change(apec.getByLabelText('Référence de ton autorisation'), { target: { value: 'Accord support APEC #1234' } });
    fireEvent.click(apec.getByLabelText('Je confirme avoir une autorisation applicable à cette collecte automatisée.'));
    expect(addButton).toBeEnabled();
    fireEvent.click(addButton);

    await waitFor(() => expect(apiMock).toHaveBeenLastCalledWith('/custom-scrapers', expect.objectContaining({ method: 'POST' })));
    const options = apiMock.mock.calls[1]?.[1] as { body: string };
    const payload = JSON.parse(options.body);
    expect(payload.enabled).toBe(false);
    expect(payload.authorizationConfirmed).toBe(true);
    expect(payload.authorizationReference).toContain('Accord support APEC #1234');
    expect(payload.maxPages).toBe(3);
    expect(payload.maxDetails).toBe(15);

    await waitFor(() => expect(screen.getByText(/a été ajoutée/i)).toBeInTheDocument());
    expect(screen.getByText(/désactivée/i)).toBeInTheDocument();
  });
});
