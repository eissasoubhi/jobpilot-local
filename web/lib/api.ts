export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? '/api';

async function readJsonResponse<T>(response: Response): Promise<T> {
  // Real fetch Responses always expose Headers. Some focused unit tests use
  // deliberately minimal Response-shaped fakes, so only enforce content type
  // when a header value is actually available.
  const contentType = response.headers?.get?.('content-type')?.toLowerCase();
  if (contentType && !contentType.includes('application/json')) {
    // Never leak raw PHP/HTML error output into the UI. The status is enough for
    // the user-facing error while server logs keep the diagnostic detail.
    throw new Error(`Le serveur a renvoyé une réponse invalide (HTTP ${response.status}).`);
  }

  try {
    return await response.json() as T;
  } catch {
    throw new Error(`Le serveur a renvoyé un JSON invalide (HTTP ${response.status}).`);
  }
}

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

  if (response.status === 204) {
    if (!response.ok) {
      throw new Error(`Erreur HTTP ${response.status}`);
    }

    return undefined as T;
  }

  if (!response.ok) {
    let payload: { error?: string; message?: string } | null = null;
    try {
      payload = await readJsonResponse<{ error?: string; message?: string }>(response);
    } catch {
      throw new Error(`Erreur HTTP ${response.status} : réponse serveur non JSON.`);
    }

    throw new Error(
      typeof payload.message === 'string' && payload.message !== ''
        ? payload.message
        : typeof payload.error === 'string' && payload.error !== ''
          ? payload.error
          : `Erreur HTTP ${response.status}`,
    );
  }

  return readJsonResponse<T>(response);
}
