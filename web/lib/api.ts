export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? '/api';

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers);

  if (init.body !== undefined && !(init.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  const response = await fetch(`${API_URL}${path}`, {
    ...init,
    headers,
    cache: 'no-store',
  });

  if (!response.ok) {
    const payload = await response
      .json()
      .catch(() => ({ error: `Erreur HTTP ${response.status}` }));

    throw new Error(
      typeof payload?.error === 'string'
        ? payload.error
        : `Erreur HTTP ${response.status}`,
    );
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return response.json() as Promise<T>;
}
