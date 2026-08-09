import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { GeminiPaidQuotaPresetPanel } from '@/components/GeminiPaidQuotaPresetPanel';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({ api: vi.fn() }));

const mockedApi = vi.mocked(api);

const legacySettings = {
  provider: 'gemini' as const,
  enabled: true,
  model: 'gemini-3.5-flash-lite',
  apiKeyConfigured: true,
  quota: { rpm: 15, tpm: 250000, rpd: 500, safetyPercent: 80 },
  quotaUsage: { rpmLimit: 12, tpmLimit: 200000, rpdLimit: 400 },
};

const tier1Settings = {
  ...legacySettings,
  quota: { rpm: 4000, tpm: 4000000, rpd: 100000, safetyPercent: 20 },
  quotaUsage: { rpmLimit: 800, tpmLimit: 800000, rpdLimit: 20000 },
};

describe('GeminiPaidQuotaPresetPanel', () => {
  beforeEach(() => {
    mockedApi.mockReset();
  });

  it('applies the observed Tier 1 Flash-Lite limits with the conservative local guard', async () => {
    mockedApi
      .mockResolvedValueOnce(legacySettings)
      .mockResolvedValueOnce(tier1Settings);

    const user = userEvent.setup();
    render(<GeminiPaidQuotaPresetPanel />);

    expect(await screen.findByText(/4[\s\u202f]?000 RPM/)).toBeInTheDocument();
    expect(screen.getByText(/100[\s\u202f]?000 RPD/)).toBeInTheDocument();
    expect(screen.getByText(/800 RPM/)).toBeInTheDocument();
    expect(screen.getByText(/20[\s\u202f]?000 RPD/)).toBeInTheDocument();

    const button = screen.getByRole('button', { name: 'Appliquer les limites Tier 1 observées' });
    await user.click(button);

    await waitFor(() => expect(mockedApi).toHaveBeenCalledTimes(2));
    expect(mockedApi.mock.calls[1][0]).toBe('/settings/ai');
    const init = mockedApi.mock.calls[1][1] as RequestInit;
    expect(init.method).toBe('PUT');
    expect(JSON.parse(String(init.body))).toEqual({
      quotaRpm: 4000,
      quotaTpm: 4000000,
      quotaRpd: 100000,
      quotaSafetyPercent: 20,
    });

    expect(await screen.findByText('Tier 1 actif')).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('garde-fou JobPilot à 20 %');
    expect(screen.getByRole('button', { name: 'Limites Tier 1 déjà actives' })).toBeDisabled();
  });

  it('does not offer to reapply the preset when it is already active', async () => {
    mockedApi.mockResolvedValueOnce(tier1Settings);

    render(<GeminiPaidQuotaPresetPanel />);

    expect(await screen.findByText('Tier 1 actif')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Limites Tier 1 déjà actives' })).toBeDisabled();
    expect(mockedApi).toHaveBeenCalledTimes(1);
  });

  it('refuses to apply Flash-Lite limits to a different Gemini model', async () => {
    mockedApi.mockResolvedValueOnce({
      ...legacySettings,
      model: 'gemini-3.6-flash',
    });

    render(<GeminiPaidQuotaPresetPanel />);

    expect(await screen.findByText('Modèle différent')).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent('gemini-3.6-flash');
    expect(screen.getByRole('button', { name: 'Preset indisponible pour ce modèle' })).toBeDisabled();
    expect(mockedApi).toHaveBeenCalledTimes(1);
  });
});
