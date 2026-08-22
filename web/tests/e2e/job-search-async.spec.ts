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

  // This is the regression that matters for the original incident: while the
  // connector work is pending, unrelated JobPilot endpoints must still answer.
  const profileStartedAt = Date.now();
  const profileResponse = await request.get('/api/profile');
  const profileDurationMs = Date.now() - profileStartedAt;

  expect(profileResponse.status()).toBe(200);
  expect(profileResponse.headers()['content-type']).toContain('application/json');
  expect(profileDurationMs).toBeLessThan(5_000);
});
