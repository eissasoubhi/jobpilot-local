import { expect, test, type Route } from '@playwright/test';

async function fulfillJson(route: Route, body: unknown): Promise<void> {
  await route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify(body),
  });
}

test('search criteria page shows and updates the effective France Travail keywords', async ({ page }) => {
  let criteria = {
    code: 'france-travail',
    name: 'France Travail',
    scope: 'GLOBAL',
    targetJobs: ['Senior Symfony Developer', 'Backend PHP/Symfony'],
    skills: ['PHP', 'Symfony'],
    effectiveQueries: ['Symfony', 'Backend PHP Symfony'],
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
});
