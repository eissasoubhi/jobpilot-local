import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import IntegrationSettingsPage from '@/app/parametres/integrations/page';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({ api: vi.fn() }));

const mockedApi = vi.mocked(api);

describe('IntegrationSettingsPage', () => {
  beforeEach(() => {
    mockedApi.mockReset();
  });

  it('stores a new Gemini key without ever redisplaying the secret', async () => {
    mockedApi
      .mockResolvedValueOnce({
        provider: 'gemini',
        enabled: false,
        model: 'gemini-3.5-flash-lite',
        apiKeyConfigured: false,
        apiKeySource: 'none',
        hasInterfaceOverrides: false,
      })
      .mockResolvedValueOnce({
        provider: 'gemini',
        enabled: true,
        model: 'gemini-3.5-flash-lite',
        apiKeyConfigured: true,
        apiKeySource: 'interface',
        hasInterfaceOverrides: true,
      });

    const user = userEvent.setup();
    render(<IntegrationSettingsPage />);

    await screen.findByText('Gemini — matching IA');
    await user.click(screen.getByLabelText('Activer le matching IA'));
    await user.type(screen.getByLabelText('Clé API Gemini'), 'test-secret-key');
    await user.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => expect(mockedApi).toHaveBeenCalledTimes(2));

    const request = mockedApi.mock.calls[1];
    expect(request[0]).toBe('/settings/ai');
    const init = request[1] as RequestInit;
    expect(init.method).toBe('PUT');
    expect(JSON.parse(String(init.body))).toEqual({
      enabled: true,
      model: 'gemini-3.5-flash-lite',
      apiKey: 'test-secret-key',
    });

    expect(screen.queryByDisplayValue('test-secret-key')).not.toBeInTheDocument();
    expect(await screen.findByText('Clé configurée')).toBeInTheDocument();
    expect(screen.getByText(/Clé enregistrée dans JobPilot/)).toBeInTheDocument();
  });
});
