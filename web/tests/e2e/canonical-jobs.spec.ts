import { expect, test, type Route } from '@playwright/test';

async function fulfillJson(route: Route, body: unknown, status = 200): Promise<void> {
  await route.fulfill({
    status,
    contentType: 'application/json',
    body: JSON.stringify(body),
  });
}

const canonicalJob = {
  id: 501,
  source: 'Source Alpha',
  sourceCode: 'source-alpha',
  sourceUrl: 'https://alpha.example/jobs/501',
  title: 'Senior PHP Symfony React Developer',
  company: 'Canonical Company',
  clientName: null,
  applicationEmail: null,
  location: 'Paris',
  contractType: 'Freelance',
  workMode: 'Hybride',
  language: 'fr',
  description: 'Mission Symfony React API Platform.',
  publishedAt: '2026-08-04T08:00:00+02:00',
  discoveredAt: '2026-08-04T08:05:00+02:00',
  ageHours: 27,
  salaryMin: null,
  salaryMax: null,
  tjmFixed: null,
  tjmMin: 480,
  tjmMax: 520,
  proposedTjm: 500,
  proposedSalary: null,
  score: 82,
  scoreReasons: ['Symfony détecté.', 'React détecté.'],
  status: 'PREPARED',
  recommendedCv: null,
  preparedAt: '2026-08-04T08:06:00+02:00',
  sourceCount: 2,
  sources: [
    {
      id: 1,
      sourceCode: 'source-alpha',
      sourceName: 'Source Alpha',
      externalId: 'alpha-501',
      sourceUrl: 'https://alpha.example/jobs/501',
      matchType: 'PRIMARY',
      matchScore: 100,
      matchReasons: ['Première occurrence de cette offre canonique.'],
      publishedAt: '2026-08-04T08:00:00+02:00',
      firstSeenAt: '2026-08-04T08:05:00+02:00',
      lastSeenAt: '2026-08-04T08:05:00+02:00',
    },
    {
      id: 2,
      sourceCode: 'source-beta',
      sourceName: 'Source Beta',
      externalId: 'beta-812',
      sourceUrl: 'https://beta.example/offres/812',
      matchType: 'SIMILARITY',
      matchScore: 91,
      matchReasons: ['Intitulé similaire à 88 %', 'Entreprise similaire à 100 %'],
      publishedAt: '2026-08-04T09:00:00+02:00',
      firstSeenAt: '2026-08-04T09:05:00+02:00',
      lastSeenAt: '2026-08-04T09:05:00+02:00',
    },
  ],
};

test('one canonical offer displays all sources and filters by any occurrence', async ({ page }) => {
  await page.route('**/api/jobs', async (route) => {
    await fulfillJson(route, [canonicalJob]);
  });
  await page.route('**/api/job-search/sync**', async (route) => {
    await fulfillJson(route, {
      configured: true,
      providers: [],
      lastSyncedAt: '2026-08-05T09:00:00+02:00',
      nextSyncAt: '2026-08-05T15:00:00+02:00',
      due: false,
      imported: 0,
      merged: 1,
      duplicates: 0,
      failed: 0,
      message: '0 nouvelle offre, 1 nouvelle source fusionnée.',
    });
  });

  await page.goto('/offres');

  await expect(page.getByRole('heading', { name: canonicalJob.title, level: 3 })).toHaveCount(1);
  await expect(page.getByText('2 sources', { exact: true })).toBeVisible();
  await expect(page.locator('span.badge').filter({ hasText: /^Source Alpha$/ })).toBeVisible();
  await expect(page.locator('span.badge').filter({ hasText: /^Source Beta$/ })).toBeVisible();
  await expect(page.getByText('1 source(s) fusionnée(s)', { exact: true })).toBeVisible();

  await page.getByLabel('Filtrer par source').selectOption({ label: 'Source Beta' });
  await expect(page.getByRole('heading', { name: canonicalJob.title, level: 3 })).toBeVisible();

  await page.getByText('Sources de cette offre (2)').click();
  await expect(page.getByText('Fusion par similarité', { exact: true })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Ouvrir sur Source Beta' })).toHaveAttribute(
    'href',
    'https://beta.example/offres/812',
  );
});
