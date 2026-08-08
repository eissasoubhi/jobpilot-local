import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import IntegrationSettingsPage from '@/app/parametres/integrations/page';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({ api: vi.fn() }));

const mockedApi = vi.mocked(api);

const emptyGemini = {
  provider: 'gemini',
  enabled: false,
  model: 'gemini-3.5-flash-lite',
  apiKeyConfigured: false,
  apiKeySource: 'none',
  hasInterfaceOverrides: false,
  quota: { rpm: 15, tpm: 250000, rpd: 500, safetyPercent: 80 },
  quotaUsage: {
    rpmUsed: 0,
    tpmUsed: 0,
    rpdUsed: 0,
    rpmLimit: 12,
    tpmLimit: 200000,
    rpdLimit: 400,
    providerRpm: 15,
    providerTpm: 250000,
    providerRpd: 500,
    safetyPercent: 80,
    resetsAt: '2026-08-09T00:00:00-07:00',
    resetTimeZone: 'America/Los_Angeles',
  },
};

const adzuna = {
  id: 'adzuna',
  label: 'Adzuna',
  category: 'connector',
  runtimeActive: true,
  note: 'Ces identifiants sont utilisés immédiatement par le connecteur Adzuna.',
  fields: {
    appId: { label: 'App ID', secret: false, configured: false, source: 'none', value: '' },
    appKey: { label: 'App key', secret: true, configured: false, source: 'none', value: null },
  },
};

describe('IntegrationSettingsPage', () => {
  beforeEach(() => {
    mockedApi.mockReset();
  });

  it('stores a new Gemini key and quota guard without ever redisplaying the secret', async () => {
    mockedApi
      .mockResolvedValueOnce(emptyGemini)
      .mockResolvedValueOnce([adzuna])
      .mockResolvedValueOnce({
        ...emptyGemini,
        enabled: true,
        apiKeyConfigured: true,
        apiKeySource: 'interface',
        hasInterfaceOverrides: true,
      });

    const user = userEvent.setup();
    render(<IntegrationSettingsPage />);

    await screen.findByText('Gemini — matching IA actif');
    expect(screen.getByText(/0\/12 requêtes\/minute/)).toBeInTheDocument();
    expect(screen.getByDisplayValue('250000')).toBeInTheDocument();

    await user.click(screen.getByLabelText('Activer le matching IA'));
    await user.type(screen.getByLabelText('Clé API Gemini'), 'test-secret-key');
    await user.click(screen.getByRole('button', { name: 'Enregistrer Gemini' }));

    await waitFor(() => expect(mockedApi).toHaveBeenCalledTimes(3));

    const request = mockedApi.mock.calls[2];
    expect(request[0]).toBe('/settings/ai');
    const init = request[1] as RequestInit;
    expect(init.method).toBe('PUT');
    expect(JSON.parse(String(init.body))).toEqual({
      enabled: true,
      model: 'gemini-3.5-flash-lite',
      quotaRpm: 15,
      quotaTpm: 250000,
      quotaRpd: 500,
      quotaSafetyPercent: 80,
      apiKey: 'test-secret-key',
    });

    expect(screen.queryByDisplayValue('test-secret-key')).not.toBeInTheDocument();
    expect(await screen.findByText('Clé configurée')).toBeInTheDocument();
    expect(screen.getByText(/Enregistré dans JobPilot/)).toBeInTheDocument();
  });

  it('stores connector credentials without redisplaying the secret', async () => {
    mockedApi
      .mockResolvedValueOnce(emptyGemini)
      .mockResolvedValueOnce([adzuna])
      .mockResolvedValueOnce({
        ...adzuna,
        fields: {
          appId: { label: 'App ID', secret: false, configured: true, source: 'interface', value: 'jobpilot-app' },
          appKey: { label: 'App key', secret: true, configured: true, source: 'interface', value: null },
        },
      });

    const user = userEvent.setup();
    render(<IntegrationSettingsPage />);

    await screen.findByText('Adzuna');
    await user.type(screen.getByLabelText('Adzuna — App ID'), 'jobpilot-app');
    await user.type(screen.getByLabelText('Adzuna — App key'), 'adzuna-secret');
    await user.click(screen.getByRole('button', { name: 'Enregistrer Adzuna' }));

    await waitFor(() => expect(mockedApi).toHaveBeenCalledTimes(3));

    const request = mockedApi.mock.calls[2];
    expect(request[0]).toBe('/settings/integrations/adzuna');
    const init = request[1] as RequestInit;
    expect(JSON.parse(String(init.body))).toEqual({
      values: { appId: 'jobpilot-app' },
      secrets: { appKey: 'adzuna-secret' },
    });

    expect(screen.queryByDisplayValue('adzuna-secret')).not.toBeInTheDocument();
    expect(await screen.findByText('Adzuna enregistré.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Supprimer app key' })).toBeInTheDocument();
  });
});
