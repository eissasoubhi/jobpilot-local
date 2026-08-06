import { expect, test, type Route } from '@playwright/test';

async function fulfillJson(route: Route, body: unknown): Promise<void> {
  await route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify(body),
  });
}

test('search criteria page edits global keys and tests France Travail queries', async ({ page }) => {
  let settings = {
    interfaceLanguage: 'fr',
    targetJobs: ['Senior Symfony Developer', 'Backend PHP/Symfony'],
    exclusions: ['Stage'],
    skills: ['PHP', 'Symfony'],
    matchingThreshold: 50,
    defaultIdfTjm: 500,
    defaultOutsideIdfTjm: 480,
    defaultRemoteTjm: 480,
    minimumFreelanceTjm: 300,
    maximumTjm: 520,
    minimumCdiSalary: 35000,
    salaryIncludesTotalCompensation: true,
    cddSalaryRule: null,
    autoPrepare: true,
    autoSubmitEnabled: false,
    autoSubmitThreshold: 60,
    autoSubmitDailyLimit: 5,
    finalSubmissionMode: 'ONE_CLICK',
  };
  let criteria = {
    code: 'france-travail',
    name: 'France Travail',
    scope: 'GLOBAL',
    targetJobs: settings.targetJobs,
    skills: settings.skills,
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
  let savedGlobalPayload: unknown = null;
  let synchronizationRequested = false;

  await page.route('**/api/settings', async (route) => {
    if (route.request().method() === 'PUT') {
      savedGlobalPayload = route.request().postDataJSON();
      settings = {
        ...settings,
        targetJobs: ['Full-Stack Symfony/React'],
        skills: ['PHP', 'React'],
        exclusions: ['Stage', 'Alternance'],
        matchingThreshold: 65,
      };
      criteria = {
        ...criteria,
        targetJobs: settings.targetJobs,
        skills: settings.skills,
        effectiveQueries: ['Full Stack Symfony React'],
        latestSearchDiagnostics: {
          ...criteria.latestSearchDiagnostics,
          matchesCurrentCriteria: false,
        },
      };
    }

    await fulfillJson(route, settings);
  });

  await page.route('**/api/connectors/france-travail/criteria', async (route) => {
    await fulfillJson(route, criteria);
  });

  await page.route('**/api/connectors/france-travail/sync', async (route) => {
    synchronizationRequested = true;
    criteria = {
      ...criteria,
      latestSearchDiagnostics: {
        startedAt: '2026-08-06T12:00:00+02:00',
        requestedQueries: 1,
        completedQueries: 1,
        queriesWithResults: 1,
        queriesWithoutResults: 0,
        received: 5,
        uniqueOffers: 4,
        matchesCurrentCriteria: true,
        queries: [
          {
            query: 'Full Stack Symfony React',
            statusCode: 206,
            outcome: 'RESULTS',
            received: 5,
            uniqueOffersAdded: 4,
          },
        ],
      },
    };

    await fulfillJson(route, {
      skipped: false,
      message: '4 nouvelle(s) offre(s), 0 nouvelle(s) source(s) fusionnée(s).',
    });
  });

  await page.goto('/criteres-recherche');

  await expect(page.getByRole('heading', { name: 'Critères de recherche', level: 1 })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Critères globaux — toutes les sources' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Clés réellement utilisées' })).toBeVisible();
  await expect(page.locator('.list-row code', { hasText: /^targetJobs$/ })).toBeVisible();
  await expect(page.locator('.list-row code', { hasText: /^skills$/ })).toBeVisible();
  await expect(page.locator('.list-row code', { hasText: /^exclusions$/ })).toBeVisible();
  await expect(page.locator('.list-row code', { hasText: /^matchingThreshold$/ })).toBeVisible();

  await page.getByLabel('Postes ciblés globaux — un par ligne').fill('Full-Stack Symfony/React');
  await page.getByLabel('Compétences globales — une par ligne').fill('PHP\nReact\nphp');
  await page.getByLabel('Exclusions locales — une par ligne').fill('Stage\nAlternance\nstage');
  await page.getByLabel('Seuil de préparation automatique').fill('65');
  await page.getByRole('button', { name: 'Enregistrer les critères globaux' }).click();

  await expect.poll(() => savedGlobalPayload).toEqual({
    targetJobs: ['Full-Stack Symfony/React'],
    skills: ['PHP', 'React'],
    exclusions: ['Stage', 'Alternance'],
    matchingThreshold: 65,
  });
  await expect(page.getByText(/Les critères globaux ont été enregistrés/)).toBeVisible();

  await expect(page.getByText('Requêtes réellement envoyées à France Travail')).toBeVisible();
  await page.getByRole('button', { name: 'Tester ces critères maintenant' }).click();

  await expect.poll(() => synchronizationRequested).toBe(true);
  await expect(page.getByText('4 nouvelle(s) offre(s), 0 nouvelle(s) source(s) fusionnée(s).')).toBeVisible();
  await expect(page.locator('span.badge.blue', { hasText: /^Full Stack Symfony React$/ })).toBeVisible();
  await expect(page.getByText('Correspond aux critères actuels')).toBeVisible();
  await expect(page.getByText('5 offre(s) reçue(s)')).toBeVisible();
  await expect(page.getByText('4 nouvelle(s) offre(s) unique(s)')).toBeVisible();
  await expect(page.getByText('0 vide(s)')).toBeVisible();
});
