import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { AiSidebarStatus } from '@/components/AiSidebarStatus';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({ api: vi.fn() }));

const mockedApi = vi.mocked(api);

describe('AiSidebarStatus', () => {
  beforeEach(() => {
    mockedApi.mockReset();
  });

  afterEach(() => {
    vi.clearAllTimers();
  });

  it('shows active AI mode and the most constrained quota percentage', async () => {
    mockedApi.mockResolvedValue({
      provider: 'gemini',
      enabled: true,
      model: 'gemini-3.5-flash-lite',
      apiKeyConfigured: true,
      quotaUsage: {
        rpmUsed: 6,
        tpmUsed: 20000,
        rpdUsed: 40,
        rpmLimit: 12,
        tpmLimit: 200000,
        rpdLimit: 400,
      },
    });

    render(<AiSidebarStatus />);

    expect(await screen.findByText('IA active')).toBeInTheDocument();
    expect(screen.getByText('Gemini · gemini-3.5-flash-lite')).toBeInTheDocument();
    expect(screen.getByText('50 %')).toBeInTheDocument();
    expect(screen.getByText('RPM 50% · TPM 10% · Jour 10%')).toBeInTheDocument();
  });

  it('shows when AI is disabled', async () => {
    mockedApi.mockResolvedValue({
      provider: 'gemini',
      enabled: false,
      model: 'gemini-3.5-flash-lite',
      apiKeyConfigured: true,
      quotaUsage: {
        rpmUsed: 0,
        tpmUsed: 0,
        rpdUsed: 0,
        rpmLimit: 12,
        tpmLimit: 200000,
        rpdLimit: 400,
      },
    });

    render(<AiSidebarStatus />);

    expect(await screen.findByText('IA désactivée')).toBeInTheDocument();
    expect(screen.getByText('0 %')).toBeInTheDocument();
  });
});
