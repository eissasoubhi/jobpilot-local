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
    reviewDueAt: '2026-11-08',
    reviewFresh: true,
    reviewTtlDays: 90,
    syncIntervalMinutes: 360,
    maxPages: 3,
    maxDetails: 15,
    gmailSupported: true,
    gmailPlatformCode: 'apec',
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
    reviewDueAt: '2026-11-08',
    reviewFresh: true,
    reviewTtlDays: 90,
    syncIntervalMinutes: 360,
    maxPages: 1,
    maxDetails: 0,
    gmailSupported: true,
    gmailPlatformCode: 'welcome-to-the-jungle',
  },
];

describe('SourcePresetPanel', () => {
  beforeEach(() => {
    apiMock.mockReset();
  });

  it('keeps assisted-only sites blocked, exposes Gmail and adds fresh authorized presets disabled', async () => {
    apiMock.mockResolvedValueOnce(presets);
    apiMock.mockResolvedValueOnce({ id: 88, name: 'APEC — PHP / Symfony' });

    render(<SourcePresetPanel />);

    await waitFor(() => expect(screen.getByText('APEC — PHP / Symfony')).toBeInTheDocument());

    const assistedCard = screen.getByText('Welcome to the Jungle').closest('.notice');
    expect(assistedCard).not.toBeNull();
    const assisted = within(assistedCard as HTMLElement);
    expect(assisted.getByText('Import assisté uniquement')).toBeInTheDocument();
    expect(assisted.getByText('Gmail pris en charge')).toBeInTheDocument();
    expect(assisted.getByRole('link', { name: 'Ouvrir Gmail JobPilot' })).toHaveAttribute('href', '/messages');
    expect(assisted.queryByRole('button', { name: 'Ajouter désactivée' })).not.toBeInTheDocument();

    const apecCard = screen.getByText('APEC — PHP / Symfony').closest('.notice');
    expect(apecCard).not.toBeNull();
    const apec = within(apecCard as HTMLElement);
    expect(apec.getByText('Gmail pris en charge')).toBeInTheDocument();
    expect(apec.getByText(/échéance : 2026-11-08/i)).toBeInTheDocument();
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

  it('blocks onboarding when the JobPilot compliance review has expired', async () => {
    apiMock.mockResolvedValueOnce([
      {
        ...presets[0],
        canPrefill: false,
        reviewFresh: false,
        reviewDueAt: '2026-11-08',
      },
    ]);

    render(<SourcePresetPanel />);

    await waitFor(() => expect(screen.getByText('APEC — PHP / Symfony')).toBeInTheDocument());
    const apecCard = screen.getByText('APEC — PHP / Symfony').closest('.notice');
    expect(apecCard).not.toBeNull();
    const apec = within(apecCard as HTMLElement);

    expect(apec.getByText('Revue expirée')).toBeInTheDocument();
    expect(apec.getByText(/dépassé 90 jours/i)).toBeInTheDocument();
    expect(apec.getByRole('link', { name: 'Ouvrir Gmail JobPilot' })).toHaveAttribute('href', '/messages');
    expect(apec.queryByLabelText('Référence de ton autorisation')).not.toBeInTheDocument();
    expect(apec.queryByRole('button', { name: 'Ajouter désactivée' })).not.toBeInTheDocument();
    expect(apiMock).toHaveBeenCalledTimes(1);
  });
});
