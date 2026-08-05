import { expect, test, type Route } from '@playwright/test';

async function fulfillJson(route: Route, body: unknown): Promise<void> {
  await route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify(body),
  });
}

test('CRM directory exposes validated contacts, filters and persistent annotations', async ({ page }) => {
  let annotation: { displayName: string; note: string; updatedAt: string } | null = null;
  let savedPayload: unknown = null;

  const directoryPayload = () => ({
    generatedAt: '2026-08-05T20:00:00+00:00',
    organizationCount: 2,
    contactCount: 1,
    annotationCount: annotation === null ? 0 : 1,
    organizations: [
      {
        key: 'acme consulting',
        name: annotation?.displayName || 'Acme Consulting',
        sourceName: 'Acme Consulting',
        annotation,
        roles: ['AGENCY', 'COMPANY'],
        offerCount: 1,
        applicationCount: 1,
        positioningCount: 1,
        messageCount: 2,
        contactCount: 1,
        applicationStatuses: { INTERVIEW: 1 },
        positioningStatuses: { AGREEMENT_GIVEN: 1 },
        lastActivityAt: '2026-08-05T10:00:00+00:00',
        contacts: [{
          key: 'jane@acme.test',
          name: 'Jane Recruiter',
          email: 'jane@acme.test',
          phone: '+33 6 00 00 00 00',
          roles: ['INBOX_CONTACT', 'RECRUITER'],
          messageCount: 2,
          lastContactAt: '2026-08-05T09:00:00+00:00',
        }],
        latestOffers: [{
          id: 42,
          title: 'Senior Symfony Developer',
          status: 'READY_TO_SUBMIT',
          score: 88,
          sourceUrl: 'https://example.test/jobs/42',
        }],
      },
      {
        key: 'final client',
        name: 'Final Client',
        sourceName: 'Final Client',
        annotation: null,
        roles: ['CLIENT'],
        offerCount: 0,
        applicationCount: 1,
        positioningCount: 1,
        messageCount: 0,
        contactCount: 0,
        applicationStatuses: { SUBMITTED: 1 },
        positioningStatuses: { MISSION_DETECTED: 1 },
        lastActivityAt: '2026-08-04T10:00:00+00:00',
        contacts: [],
        latestOffers: [],
      },
    ],
  });

  await page.route('**/api/crm/organizations/*/annotation', async (route) => {
    savedPayload = route.request().postDataJSON();
    const payload = savedPayload as { displayName?: string; note?: string };
    annotation = payload.displayName || payload.note
      ? {
          displayName: payload.displayName ?? '',
          note: payload.note ?? '',
          updatedAt: '2026-08-05T21:00:00+00:00',
        }
      : null;

    await fulfillJson(route, {
      organizationKey: 'acme consulting',
      annotation,
    });
  });

  await page.route('**/api/crm/organizations', async (route) => {
    await fulfillJson(route, directoryPayload());
  });

  await page.goto('/crm');

  await expect(page.getByRole('heading', { name: 'CRM', level: 1 })).toBeVisible();
  await expect(page.getByText('Données sources protégées.')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Acme Consulting' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Final Client' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'jane@acme.test' })).toHaveAttribute('href', 'mailto:jane@acme.test');
  await expect(page.getByRole('link', { name: '+33 6 00 00 00 00' })).toHaveAttribute('href', 'tel:+33 6 00 00 00 00');
  await expect(page.getByRole('link', { name: 'Ouvrir l’offre' })).toHaveAttribute('href', 'https://example.test/jobs/42');

  await page.getByLabel('Rôle de l’organisation').selectOption('CLIENT');
  await expect(page.getByRole('heading', { name: 'Final Client' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Acme Consulting' })).not.toBeVisible();

  await page.getByLabel('Rechercher une organisation, un contact, une note ou une offre').fill('Jane Recruiter');
  await expect(page.getByText('Aucune organisation ne correspond à ces critères.')).toBeVisible();

  await page.getByLabel('Rôle de l’organisation').selectOption('ALL');
  await expect(page.getByRole('heading', { name: 'Acme Consulting' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Final Client' })).not.toBeVisible();

  await page.getByLabel('Rechercher une organisation, un contact, une note ou une offre').fill('');
  await page.getByRole('button', { name: 'Ajouter une note CRM' }).first().click();
  const annotationDialog = page.getByRole('dialog', { name: 'Modifier la fiche CRM Acme Consulting' });
  await expect(annotationDialog).toBeVisible();
  await expect(annotationDialog.locator('.notice')).toContainText('Nom détecté dans les données sources : Acme Consulting');
  await expect(annotationDialog.getByTestId('crm-organization-key')).toHaveText('acme consulting');

  await page.getByLabel('Nom affiché dans le CRM').fill('ACME Consulting France');
  await page.getByLabel('Note interne').fill('Contact prioritaire pour les missions Symfony.');
  await page.getByRole('button', { name: 'Enregistrer la fiche CRM' }).click();

  await expect.poll(() => savedPayload).toEqual({
    displayName: 'ACME Consulting France',
    note: 'Contact prioritaire pour les missions Symfony.',
  });
  await expect(page.getByRole('heading', { name: 'ACME Consulting France' })).toBeVisible();
  await expect(page.getByText('Nom source : Acme Consulting')).toBeVisible();
  await expect(page.getByText('Contact prioritaire pour les missions Symfony.')).toBeVisible();
  await expect(page.getByText('La fiche CRM de ACME Consulting France a été enregistrée.')).toBeVisible();
  const annotatedMetric = page.getByText('Fiches annotées').locator('..').locator('.metric');
  await expect(annotatedMetric).toHaveText('1');

  await page.getByLabel('Rechercher une organisation, un contact, une note ou une offre').fill('missions Symfony');
  await expect(page.getByRole('heading', { name: 'ACME Consulting France' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Final Client' })).not.toBeVisible();
});
