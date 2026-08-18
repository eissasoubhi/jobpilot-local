import { expect, test } from '@playwright/test';

function watchForBrowserFailures(page: import('@playwright/test').Page): string[] {
  const failures: string[] = [];

  page.on('pageerror', (error) => failures.push(`pageerror: ${error.message}`));
  page.on('console', (message) => {
    if (message.type() === 'error') failures.push(`console.error: ${message.text()}`);
  });

  return failures;
}

test('dashboard loads without browser errors', async ({ page }) => {
  const failures = watchForBrowserFailures(page);

  await page.goto('/');
  await expect(page.getByRole('heading', { name: 'Tableau de bord' })).toBeVisible();

  expect(failures).toEqual([]);
});

test('profile, CV, job preparation, source filtering, guided submission and positioning workflow', async ({ page }, testInfo) => {
  const failures = watchForBrowserFailures(page);
  const uniqueSuffix = `${testInfo.workerIndex}-${Date.now()}-${testInfo.retry}`;
  const cvName = `CV Symfony React FR ${uniqueSuffix}`;
  const jobTitle = `Senior Symfony React Developer ${uniqueSuffix}`;
  const rejectedJobTitle = `Stage PHP ${uniqueSuffix}`;
  const sourceA = `Source A ${uniqueSuffix}`;
  const sourceB = `Source B ${uniqueSuffix}`;
  const positioningTitle = `Mission Symfony React ${uniqueSuffix}`;
  const tenderReference = `AO-E2E-${uniqueSuffix}`;
  const sourceUrl = `https://example.test/jobs/${uniqueSuffix}`;

  await page.goto('/profil');
  await expect(page.getByLabel('Nom complet')).toHaveValue('Demo Candidate');
  await page.getByLabel('Ville').fill('Paris');
  await page.getByRole('button', { name: 'Enregistrer' }).click();
  await expect(page.getByText('Profil enregistré.')).toBeVisible();

  await page.goto('/cv');
  await page.getByLabel('Nom du CV').fill(cvName);
  await page.getByLabel('Tags').fill('Symfony, React, PHP');
  await page.getByLabel('Fichier PDF ou Word').setInputFiles({
    name: `cv-fr-${uniqueSuffix}.pdf`,
    mimeType: 'application/pdf',
    buffer: Buffer.from('%PDF-1.4\n% JobPilot test CV\n'),
  });
  await page.getByLabel('CV par défaut pour cette langue').check();
  await page.getByRole('button', { name: 'Téléverser' }).click();
  await expect(page.getByRole('heading', { name: cvName, level: 3, exact: true })).toBeVisible();

  await page.goto('/offres');
  await page.getByRole('button', { name: 'Ajouter une offre' }).click();
  const dialog = page.getByRole('dialog', { name: 'Ajouter une offre' });
  await dialog.getByLabel('Source', { exact: true }).fill(sourceA);
  await dialog.getByLabel('URL').fill(sourceUrl);
  await dialog.getByLabel('Intitulé').fill(jobTitle);
  await dialog.getByLabel('Entreprise').fill('Example Company');
  await dialog.getByLabel('Lieu').fill('Paris');
  await dialog.getByLabel('Contrat').selectOption({ label: 'Freelance' });
  await dialog.getByLabel('TJM minimum').fill('480');
  await dialog.getByLabel('TJM maximum').fill('600');
  await dialog.getByLabel('Description').fill('Nous recherchons un développeur senior PHP Symfony React TypeScript Docker API Platform avec 11 ans d’expérience.');
  await dialog.getByRole('button', { name: 'Analyser et enregistrer' }).click();

  const jobHeading = page.getByRole('heading', { name: jobTitle, level: 3, exact: true });
  await expect(jobHeading).toBeVisible();
  const jobRow = jobHeading.locator('xpath=ancestor::div[contains(@class,"list-row")]');
  await expect(jobRow.getByText('TJM proposé : 500 €')).toBeVisible();
  await expect(jobRow.getByText('PREPARED')).toBeVisible();

  await page.getByRole('button', { name: 'Ajouter une offre' }).click();
  const secondDialog = page.getByRole('dialog', { name: 'Ajouter une offre' });
  await secondDialog.getByLabel('Source', { exact: true }).fill(sourceB);
  await secondDialog.getByLabel('Intitulé').fill(rejectedJobTitle);
  await secondDialog.getByLabel('Entreprise').fill('Second Company');
  await secondDialog.getByLabel('Lieu').fill('Paris');
  await secondDialog.getByLabel('Contrat').selectOption({ label: 'CDD' });
  await secondDialog.getByLabel('Description').fill('Stage junior PHP Symfony.');
  await secondDialog.getByRole('button', { name: 'Analyser et enregistrer' }).click();

  const rejectedHeading = page.getByRole('heading', { name: rejectedJobTitle, level: 3, exact: true });
  await expect(rejectedHeading).toBeVisible();

  const sourceFilter = page.getByLabel('Filtrer par source');
  await sourceFilter.selectOption({ label: sourceA });
  await expect(jobHeading).toBeVisible();
  await expect(rejectedHeading).toBeHidden();
  await sourceFilter.selectOption({ label: sourceB });
  await expect(jobHeading).toBeHidden();
  await expect(rejectedHeading).toBeVisible();
  await sourceFilter.selectOption({ label: 'Toutes les sources' });

  await page.getByRole('button', { name: 'À examiner' }).click();
  await expect(jobHeading).toBeHidden();
  await expect(rejectedHeading).toBeHidden();
  await page.getByRole('button', { name: 'Préparées' }).click();
  await expect(jobHeading).toBeVisible();

  await jobRow.getByRole('button', { name: 'Examiner' }).click();
  await expect(page).toHaveURL(/\/offres\/review/);
  await expect(page.getByRole('heading', { name: jobTitle })).toBeVisible();
  await expect(page.getByText('1 / 1')).toBeVisible();
  await page.getByRole('button', { name: 'Envoyée' }).click();
  await expect(page.getByText(/Aucune candidature prête à examiner/i)).toBeVisible();

  await page.goto('/positionnements');
  await page.getByRole('button', { name: 'Ajouter un positionnement' }).click();
  const positioningDialog = page.getByRole('dialog', { name: 'Ajouter un positionnement' });
  await positioningDialog.getByLabel('Intitulé').fill(positioningTitle);
  await positioningDialog.getByLabel('Entreprise / client').fill('Example Consulting');
  await positioningDialog.getByLabel('Référence AO').fill(tenderReference);
  await positioningDialog.getByLabel('Statut').selectOption('SENT');
  await positioningDialog.getByRole('button', { name: 'Enregistrer' }).click();
  await expect(page.getByRole('heading', { name: positioningTitle, level: 3, exact: true })).toBeVisible();
  await expect(page.getByText(tenderReference, { exact: true })).toBeVisible();

  expect(failures).toEqual([]);
});
