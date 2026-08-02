import { expect, test, type Page } from '@playwright/test';

function watchForBrowserFailures(page: Page): string[] {
  const failures: string[] = [];

  page.on('pageerror', (error) => failures.push(`pageerror: ${error.message}`));
  page.on('console', (message) => {
    if (message.type() === 'error') failures.push(`console: ${message.text()}`);
  });
  page.on('response', (response) => {
    if (response.status() >= 500) failures.push(`http ${response.status()}: ${response.url()}`);
  });

  return failures;
}

test('all main pages load without browser or server errors', async ({ page }) => {
  const failures = watchForBrowserFailures(page);
  const routes = [
    ['/', 'Tableau de bord'],
    ['/offres', 'Offres'],
    ['/candidatures', 'Candidatures'],
    ['/positionnements', 'Positionnements'],
    ['/messages', 'Messagerie'],
    ['/cv', 'Mes CV'],
    ['/profil', 'Profil candidat'],
    ['/parametres', 'Paramètres'],
  ] as const;

  for (const [route, heading] of routes) {
    await page.goto(route);
    await expect(page.getByRole('heading', { name: heading, level: 1 })).toBeVisible();

    if (route === '/offres') {
      await expect(page.getByRole('button', { name: 'Rechercher maintenant' })).toBeVisible();
      await expect(page.getByText('Recherche automatique', { exact: true })).toBeVisible();
    }
  }

  expect(failures).toEqual([]);
});

test('profile, CV, job preparation and positioning workflow', async ({ page }) => {
  const failures = watchForBrowserFailures(page);

  await page.goto('/profil');
  await expect(page.getByLabel('Nom complet')).toHaveValue('Aissa SOUBHI');
  await page.getByLabel('Ville').fill('Cergy');
  await page.getByRole('button', { name: 'Enregistrer' }).click();
  await expect(page.getByText('Profil enregistré.')).toBeVisible();

  await page.goto('/cv');
  await page.getByLabel('Nom du CV').fill('CV Symfony React FR');
  await page.getByLabel('Tags').fill('Symfony, React, PHP');
  await page.getByLabel('Fichier PDF ou Word').setInputFiles({
    name: 'cv-fr.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.from('%PDF-1.4\n% JobPilot test CV\n'),
  });
  await page.getByLabel('CV par défaut pour cette langue').check();
  await page.getByRole('button', { name: 'Téléverser' }).click();
  await expect(page.getByRole('heading', { name: 'CV Symfony React FR', level: 3 })).toBeVisible();

  await page.goto('/offres');
  await page.getByRole('button', { name: 'Ajouter une offre' }).click();
  const dialog = page.getByRole('dialog', { name: 'Ajouter une offre' });
  await dialog.getByLabel('Intitulé').fill('Senior Symfony React Developer');
  await dialog.getByLabel('Entreprise').fill('Example Company');
  await dialog.getByLabel('Lieu').fill('Paris');
  await dialog.getByLabel('Contrat').selectOption({ label: 'Freelance' });
  await dialog.getByLabel('TJM minimum').fill('480');
  await dialog.getByLabel('TJM maximum').fill('600');
  await dialog.getByLabel('Description').fill('Nous recherchons un développeur senior PHP Symfony React TypeScript Docker API Platform avec 11 ans d’expérience.');
  await dialog.getByRole('button', { name: 'Analyser et enregistrer' }).click();

  const jobHeading = page.getByRole('heading', { name: 'Senior Symfony React Developer', level: 3 });
  await expect(jobHeading).toBeVisible();
  const jobRow = jobHeading.locator('xpath=ancestor::div[contains(@class,"list-row")]');
  await expect(jobRow.getByText('TJM proposé : 520 €')).toBeVisible();
  await expect(jobRow.getByText('PREPARED')).toBeVisible();

  await page.goto('/candidatures');
  await expect(page.getByRole('heading', { name: 'Senior Symfony React Developer', level: 3 })).toBeVisible();
  await page.getByRole('button', { name: 'Ouvrir' }).click();
  await expect(page.getByRole('dialog', { name: 'Candidature Senior Symfony React Developer' })).toBeVisible();
  await page.getByLabel('Confirmation / référence').fill('CONF-TEST-001');
  await page.getByRole('button', { name: 'Enregistrer' }).click();

  await page.goto('/positionnements');
  await page.getByRole('button', { name: 'Nouveau positionnement' }).click();
  const positioningDialog = page.getByRole('dialog', { name: 'Nouveau positionnement' });
  await positioningDialog.getByLabel('Client final').fill('France Télévisions');
  await positioningDialog.getByLabel('Agence / ESN').fill('Agence Test');
  await positioningDialog.getByLabel('Commercial').fill('Jean Dupont');
  await positioningDialog.getByLabel('Référence appel d’offres').fill('AO-E2E-001');
  await positioningDialog.getByLabel('Intitulé de la mission').fill('Mission Symfony React');
  await positioningDialog.getByLabel('Description').fill('Mission Symfony React pour une plateforme média.');
  await positioningDialog.getByLabel('TJM fixe').fill('450');
  await positioningDialog.getByLabel('Lieu').fill('Paris');
  await positioningDialog.getByLabel('Statut').selectOption('AGREEMENT_GIVEN');
  await positioningDialog.getByRole('button', { name: 'Enregistrer' }).click();

  await expect(page.getByRole('heading', { name: 'Mission Symfony React', level: 3 })).toBeVisible();
  await expect(page.getByText('450 €')).toBeVisible();

  expect(failures).toEqual([]);
});
