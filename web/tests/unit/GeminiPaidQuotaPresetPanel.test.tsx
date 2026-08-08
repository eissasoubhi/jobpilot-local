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

const paidSettings = {
  ...legacySettings,
  quota: { rpm: 60, tpm: 1000000, rpd: 2000, safetyPercent: 80 },
  quotaUsage: { rpmLimit: 48, tpmLimit: 800000, rpdLimit: 1600 },
};

describe('GeminiPaidQuotaPresetPanel', () => {
  beforeEach(() => {
    mockedApi.mockReset();
  });

  it('applies the balanced paid preset immediately', async () => {
    mockedApi
      .mockResolvedValueOnce(legacySettings)
      .mockResolvedValueOnce(paidSettings);

    const user = userEvent.setup();
    render(<GeminiPaidQuotaPresetPanel />);

    const button = await screen.findByRole('button', { name: 'Appliquer le profil payant recommandé' });
    await user.click(button);

    await waitFor(() => expect(mockedApi).toHaveBeenCalledTimes(2));
    expect(mockedApi.mock.calls[1][0]).toBe('/settings/ai');
    const init = mockedApi.mock.calls[1][1] as RequestInit;
    expect(init.method).toBe('PUT');
    expect(JSON.parse(String(init.body))).toEqual({
      quotaRpm: 60,
      quotaTpm: 1000000,
      quotaRpd: 2000,
      quotaSafetyPercent: 80,
    });

    expect(await screen.findByText('Profil payant actif')).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('Profil Gemini payant appliqué');
    expect(screen.getByRole('button', { name: 'Profil payant déjà actif' })).toBeDisabled();
  });

  it('does not offer to reapply the preset when it is already active', async () => {
    mockedApi.mockResolvedValueOnce(paidSettings);

    render(<GeminiPaidQuotaPresetPanel />);

    expect(await screen.findByText('Profil payant actif')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Profil payant déjà actif' })).toBeDisabled();
    expect(mockedApi).toHaveBeenCalledTimes(1);
  });
});
