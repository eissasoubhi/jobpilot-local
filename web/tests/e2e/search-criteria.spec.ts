import { expect, test, type Route } from '@playwright/test';

async function fulfillJson(route: Route, body: unknown): Promise<void> {
  await route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify(body),
  });
}

test('search criteria page shows query performance and updates France Travail keywords', async ({ page }) => {
  let criteria = {
    code: 'france-travail',
    name: 'France Travail',
    scope: 'GLOBAL',
    targetJobs: ['Senior Symfony Developer', 'Backend PHP/Symfony'],
    skills: ['PHP', 'Symfony'],
    effectiveQueries: ['Symfony', 'Backend PHP Symfony'],
    latestSearchDiagnostics: {
      startedAt: '2026-08-06T09:00:00+02:00',
      requestedQueries: 2,
      completedQueries: 2,
      queriesWithResults: 1,
      queriesWithoutResults: 1,
      received: 3,
      uniqueOffers: 2,
      matchesCurrentCriteria: true,
      queries: [
        {
          query: 'Symfony',
          statusCode: 204,
          outcome: 'NO_RESULTS',
          received: 0,
          uniqueOffersAdded: 0,
        },
        {
          query: 'Backend PHP Symfony',
          statusCode: 206,
          outcome: 'RESULTS',
          received: 3,
          uniqueOffersAdded: 2,
        },
      ],
    },
    fixedCriteria: [
      { key: 'sort', label: 'Tri', value: 'Offres les plus récentes' },
      { key: 'limit', label: 'Limite', value: '6 requêtes maximum par synchronisation' },
    ],
    limits: {
      maxItemsPerList: 20,
      maxItemLength: 120,
      maxEffectiveQueries: 6,
    },
    note: 'Ces intitulés et compétences sont les critères globaux de JobPilot.',
  };
  let savedPayload: unknown = null;

  await page.route('**/api/connectors/france-travail/criteria', async (route) => {
    if (route.request().method() === 'PUT') {
      savedPayload = route.request().postDataJSON();
      criteria = {
        ...criteria,
        targetJobs: ['Full-Stack Symfony/React'],
        skills: ['PHP', 'React'],
        effectiveQueries: ['Full Stack Symfony React'],
        latestSearchDiagnostics: {
          ...criteria.latestSearchDiagnostics,
          matchesCurrentCriteria: false,
        },
      };
    }

    await fulfillJson(route, criteria);
  });

  await page.goto('/criteres-recherche');

  await expect(page.getByRole('heading', { name: 'Critères de recherche', level: 1 })).toBeVisible();
  await expect(page.getByText('Requêtes réellement envoyées à France Travail')).toBeVisible();
  await expect(page.getByText('Symfony', { exact: true })).toBeVisible();
  await expect(page.getByText('Backend PHP Symfony', { exact: true })).toBeVisible();
  await expect(page.getByText('Tri : Offres les plus récentes')).toBeVisible();
  await expect(page.getByText('Performance de la dernière synchronisation')).toBeVisible();
  await expect(page.getByText('Aucun résultat')).toBeVisible();
  await expect(page.getByText('3 offre(s) reçue(s)')).toBeVisible();
  await expect(page.getByText('2 nouvelle(s) offre(s) unique(s)')).toBeVisible();
  await expect(page.getByText('Correspond aux critères actuels')).toBeVisible();

  await page.getByRole('button', { name: 'Modifier les critères' }).click();
  await page.getByLabel('Intitulés ciblés — un par ligne').fill('Full-Stack Symfony/React');
  await page.getByLabel('Compétences de repli — une par ligne').fill('PHP\nReact\nphp');
  await page.getByRole('button', { name: 'Enregistrer les critères' }).click();

  await expect.poll(() => savedPayload).toEqual({
    targetJobs: ['Full-Stack Symfony/React'],
    skills: ['PHP', 'React'],
  });
  await expect(page.getByText('Les critères de recherche ont été enregistrés.')).toBeVisible();
  await expect(page.getByText('Full Stack Symfony React', { exact: true })).toBeVisible();
  await expect(page.getByText('Full-Stack Symfony/React', { exact: true })).toBeVisible();
  await expect(page.getByText('Critères modifiés depuis ce test')).toBeVisible();
});
