import { expect, test, type Route } from '@playwright/test';

async function fulfillJson(route: Route, body: unknown): Promise<void> {
  await route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify(body),
  });
}

test('CRM directory exposes validated contacts and searchable organization roles', async ({ page }) => {
  await page.route('**/api/crm/organizations', async (route) => {
    await fulfillJson(route, {
      generatedAt: '2026-08-05T20:00:00+00:00',
      organizationCount: 2,
      contactCount: 1,
      organizations: [
        {
          key: 'acme consulting',
          name: 'Acme Consulting',
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
  });

  await page.goto('/crm');

  await expect(page.getByRole('heading', { name: 'CRM', level: 1 })).toBeVisible();
  await expect(page.getByText('Annuaire local en lecture seule.')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Acme Consulting' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Final Client' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'jane@acme.test' })).toHaveAttribute('href', 'mailto:jane@acme.test');
  await expect(page.getByRole('link', { name: '+33 6 00 00 00 00' })).toHaveAttribute('href', 'tel:+33 6 00 00 00 00');
  await expect(page.getByRole('link', { name: 'Ouvrir l’offre' })).toHaveAttribute('href', 'https://example.test/jobs/42');

  await page.getByLabel('Rôle de l’organisation').selectOption('CLIENT');
  await expect(page.getByRole('heading', { name: 'Final Client' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Acme Consulting' })).not.toBeVisible();

  await page.getByLabel('Rechercher une organisation, un contact ou une offre').fill('Jane Recruiter');
  await expect(page.getByText('Aucune organisation ne correspond à ces critères.')).toBeVisible();

  await page.getByLabel('Rôle de l’organisation').selectOption('ALL');
  await expect(page.getByRole('heading', { name: 'Acme Consulting' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Final Client' })).not.toBeVisible();

  await page.getByLabel('Rôle de l’organisation').selectOption('AGENCY');
  await expect(page.getByRole('heading', { name: 'Acme Consulting' })).toBeVisible();
});
