import { afterEach, describe, expect, it, vi } from 'vitest';

import { api } from '@/lib/api';

describe('api', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('returns parsed JSON for a successful request', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(
      JSON.stringify({ status: 'ok' }),
      { status: 200, headers: { 'Content-Type': 'application/json' } },
    )));

    await expect(api<{ status: string }>('/health')).resolves.toEqual({ status: 'ok' });
    expect(fetch).toHaveBeenCalledWith('/api/health', expect.objectContaining({ cache: 'no-store' }));
  });

  it('adds the JSON content type when the body is not FormData', async () => {
    const mockedFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }));
    vi.stubGlobal('fetch', mockedFetch);

    await api('/settings', { method: 'PUT', body: JSON.stringify({ value: 1 }) });

    const init = mockedFetch.mock.calls[0][1] as RequestInit;
    expect(new Headers(init.headers).get('Content-Type')).toBe('application/json');
  });

  it('throws the API error message', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(
      JSON.stringify({ error: 'Requête invalide' }),
      { status: 422, headers: { 'Content-Type': 'application/json' } },
    )));

    await expect(api('/jobs')).rejects.toThrow('Requête invalide');
  });
});
