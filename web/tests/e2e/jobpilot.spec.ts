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
    ['/connecteurs', 'Connecteurs'],
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
      await expect(page.getByLabel('Filtrer par source')).toBeVisible();
    }

    if (route === '/connecteurs') {
      await expect(page.getByRole('heading', { name: 'Historique récent', level: 2 })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Arbeitnow', level: 3 })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Adzuna', level: 3 })).toBeVisible();
    }
  }

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
  await expect(jobRow.getByText('TJM proposé : 520 €')).toBeVisible();
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
  await sourceFilter.selectOption('all');
  await expect(jobHeading).toBeVisible();

  await page.goto('/candidatures');
  const applicationHeading = page.getByRole('heading', { name: jobTitle, level: 3, exact: true });
  await expect(applicationHeading).toBeVisible();
  const applicationRow = applicationHeading.locator('xpath=ancestor::div[contains(@class,"list-row")]');
  await applicationRow.getByRole('button', { name: 'Examiner et postuler' }).click();

  const applicationDialog = page.getByRole('dialog', { name: `Candidature ${jobTitle}` });
  await expect(applicationDialog).toBeVisible();
  await expect(applicationDialog.getByText('JobPilot n’envoie pas automatiquement la candidature.', { exact: true })).toBeVisible();
  await expect(applicationDialog.getByText('Offre concernée', { exact: true })).toBeVisible();
  await expect(applicationDialog.getByRole('heading', { name: jobTitle, level: 2, exact: true })).toBeVisible();
  await expect(applicationDialog.getByText('Example Company', { exact: true })).toBeVisible();
  await expect(applicationDialog.getByText('Freelance', { exact: true })).toBeVisible();
  await expect(applicationDialog.getByText('Paris', { exact: true })).toBeVisible();
  await expect(applicationDialog.getByRole('link', { name: 'Étape 2 — Ouvrir la plateforme pour postuler' })).toHaveAttribute('href', sourceUrl);
  const descriptionDetails = applicationDialog.locator('details').filter({
    hasText: 'Afficher la description complète de l’offre',
  });
  await descriptionDetails.locator('summary').click();
  await expect(descriptionDetails.locator('div.small')).toContainText('API Platform');

  await applicationDialog.getByLabel('Confirmation / référence obtenue après l’envoi').fill(`CONF-${uniqueSuffix}`);
  await applicationDialog.getByRole('button', { name: 'Étape 1 — Enregistrer mes modifications' }).click();
  await expect(applicationDialog.getByText('Modifications enregistrées. Tu peux maintenant postuler sur la plateforme d’origine.')).toBeVisible();

  page.once('dialog', async (confirmation) => {
    expect(confirmation.message()).toContain('JobPilot va enregistrer le suivi');
    await confirmation.accept();
  });
  await applicationDialog.getByRole('button', { name: 'Étape 3 — J’ai envoyé la candidature' }).click();
  await expect(applicationDialog.getByText(/Candidature marquée comme envoyée/)).toBeVisible();
  await expect(applicationDialog.getByRole('button', { name: 'Candidature déjà marquée comme envoyée' })).toBeDisabled();

  await page.goto('/positionnements');
  await page.getByRole('button', { name: 'Nouveau positionnement' }).click();
  const positioningDialog = page.getByRole('dialog', { name: 'Nouveau positionnement' });
  await positioningDialog.getByLabel('Client final').fill('France Télévisions');
  await positioningDialog.getByLabel('Agence / ESN').fill('Agence Test');
  await positioningDialog.getByLabel('Commercial', { exact: true }).fill('Jean Dupont');
  await positioningDialog.getByLabel('Référence appel d’offres').fill(tenderReference);
  await positioningDialog.getByLabel('Intitulé de la mission').fill(positioningTitle);
  await positioningDialog.getByLabel('Description').fill('Mission Symfony React pour une plateforme média.');
  await positioningDialog.getByLabel('TJM fixe').fill('450');
  await positioningDialog.getByLabel('Lieu').fill('Paris');
  await positioningDialog.getByLabel('Statut', { exact: true }).selectOption('AGREEMENT_GIVEN');
  await positioningDialog.getByRole('button', { name: 'Enregistrer' }).click();

  const positioningHeading = page.getByRole('heading', { name: positioningTitle, level: 3, exact: true });
  await expect(positioningHeading).toBeVisible();
  const positioningRow = positioningHeading.locator('xpath=ancestor::div[contains(@class,"list-row")]');
  await expect(positioningRow.getByText('450 €')).toBeVisible();

  expect(failures).toEqual([]);
});
