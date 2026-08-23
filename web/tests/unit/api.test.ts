import { afterEach, describe, expect, it, vi } from 'vitest';

import { api } from '@/lib/api';

describe('api', () => {
  afterEach(() => {
    vi.useRealTimers();
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

  it('does not expose an HTML/PHP error body when a JSON endpoint crashes', async () => {
    const fatalBody = '<br /><b>Fatal error</b>: Maximum execution time exceeded in HttpClientTrait.php';
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(
      fatalBody,
      { status: 500, headers: { 'Content-Type': 'text/html; charset=UTF-8' } },
    )));

    let message = '';
    try {
      await api('/job-search/sync?force=1');
    } catch (caughtError) {
      message = caughtError instanceof Error ? caughtError.message : String(caughtError);
    }

    expect(message).toBe('Erreur HTTP 500 : réponse serveur non JSON.');
    expect(message).not.toContain('Fatal error');
    expect(message).not.toContain('HttpClientTrait.php');
  });

  it('fails a stalled GET instead of leaving the UI loading forever', async () => {
    vi.useFakeTimers();
    vi.stubGlobal('fetch', vi.fn((_url: RequestInfo | URL, init?: RequestInit) => new Promise<Response>((_resolve, reject) => {
      init?.signal?.addEventListener('abort', () => {
        reject(new DOMException('Aborted', 'AbortError'));
      });
    })));

    const rejection = expect(api('/dashboard')).rejects.toThrow('Le serveur local ne répond pas');
    await vi.advanceTimersByTimeAsync(15_000);

    await rejection;
  });
});
