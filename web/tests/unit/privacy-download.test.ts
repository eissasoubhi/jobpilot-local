import { afterEach, describe, expect, it, vi } from 'vitest';

import { downloadWithCleanProvenance } from '@/lib/privacy-download';

afterEach(() => {
  vi.restoreAllMocks();
  document.body.innerHTML = '';
});

describe('downloadWithCleanProvenance', () => {
  it('fetches without referrer and downloads through a data URL', async () => {
    const clicks: HTMLAnchorElement[] = [];
    const originalCreateElement = document.createElement.bind(document);
    vi.spyOn(document, 'createElement').mockImplementation(((tagName: string) => {
      const element = originalCreateElement(tagName);
      if (tagName.toLowerCase() === 'a') {
        vi.spyOn(element as HTMLAnchorElement, 'click').mockImplementation(() => {
          clicks.push(element as HTMLAnchorElement);
        });
      }
      return element;
    }) as typeof document.createElement);

    const fetchImpl = vi.fn(async () => new Response('private export', {
      status: 200,
      headers: { 'Content-Type': 'application/pdf' },
    }));

    const result = await downloadWithCleanProvenance({
      url: '/api/applications/42/cover-letter/download/pdf',
      filename: 'lettre.pdf',
      fetchImpl: fetchImpl as unknown as typeof fetch,
    });

    expect(fetchImpl).toHaveBeenCalledWith(
      '/api/applications/42/cover-letter/download/pdf',
      expect.objectContaining({
        credentials: 'same-origin',
        referrerPolicy: 'no-referrer',
      }),
    );
    expect(result).toEqual({ privacyClean: true, fallbackUsed: false });
    expect(clicks).toHaveLength(1);
    expect(clicks[0].href).toMatch(/^data:application\/pdf/);
    expect(clicks[0].download).toBe('lettre.pdf');
    expect(clicks[0].rel).toBe('noreferrer');
    expect(clicks[0].referrerPolicy).toBe('no-referrer');
  });

  it('falls back to the classic URL without weakening browser security when preparation fails', async () => {
    const clicks: HTMLAnchorElement[] = [];
    const originalCreateElement = document.createElement.bind(document);
    vi.spyOn(document, 'createElement').mockImplementation(((tagName: string) => {
      const element = originalCreateElement(tagName);
      if (tagName.toLowerCase() === 'a') {
        vi.spyOn(element as HTMLAnchorElement, 'click').mockImplementation(() => {
          clicks.push(element as HTMLAnchorElement);
        });
      }
      return element;
    }) as typeof document.createElement);

    const fetchImpl = vi.fn(async () => new Response('forbidden', { status: 403 }));

    const result = await downloadWithCleanProvenance({
      url: '/api/applications/42/cover-letter/download/pdf',
      filename: 'lettre.pdf',
      fetchImpl: fetchImpl as unknown as typeof fetch,
    });

    expect(result).toEqual({ privacyClean: false, fallbackUsed: true });
    expect(clicks).toHaveLength(1);
    expect(clicks[0].getAttribute('href')).toBe('/api/applications/42/cover-letter/download/pdf');
    expect(clicks[0].download).toBe('lettre.pdf');
    expect(clicks[0].rel).toBe('noreferrer');
    expect(clicks[0].referrerPolicy).toBe('no-referrer');
  });
});
