import { expect, test } from '@playwright/test';

test('job search sync is queued immediately and does not block the HTTP API', async ({ request }) => {
  const startedAt = Date.now();
  const syncResponse = await request.post('/api/job-search/sync?force=1');
  const enqueueDurationMs = Date.now() - startedAt;

  expect(syncResponse.status()).toBe(202);
  expect(syncResponse.headers()['content-type']).toContain('application/json');
  expect(enqueueDurationMs).toBeLessThan(5_000);

  const payload = await syncResponse.json() as {
    job?: { id?: string; status?: string };
  };
  expect(payload.job?.id).toBeTruthy();
  expect(['queued', 'running']).toContain(payload.job?.status);

  const profileStartedAt = Date.now();
  const profileResponse = await request.get('/api/profile');
  const profileDurationMs = Date.now() - profileStartedAt;

  expect(profileResponse.status()).toBe(200);
  expect(profileResponse.headers()['content-type']).toContain('application/json');
  expect(profileDurationMs).toBeLessThan(5_000);
});

test('offers page reconnects to the current synchronization after reload', async ({ page, request }) => {
  const syncResponse = await request.post('/api/job-search/sync?force=1');
  expect(syncResponse.status()).toBe(202);

  await page.goto('/offres');
  const syncTitle = page.getByText('Synchronisation des offres', { exact: true });
  await expect(syncTitle).toBeVisible();
  const syncHeader = syncTitle.locator('..');
  await expect(syncHeader.getByText(/Mise en file|Worker actif|Terminée|Échec/)).toBeVisible();

  await page.reload();
  await expect(page.getByText('Synchronisation des offres', { exact: true })).toBeVisible();
  await expect(page.getByLabel(/Progression de la synchronisation/)).toBeVisible();
});
