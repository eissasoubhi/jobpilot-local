import { expect, test } from '@playwright/test';

test('application timeline shows only persisted business events for the selected offer', async ({ page }) => {
  await page.route('**/api/applications', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify([
        {
          id: 12,
          jobOffer: {
            id: 42,
            title: 'Senior Symfony Engineer',
            company: 'Example Company',
            url: 'https://example.test/jobs/42',
          },
          status: 'INTERVIEW',
          source: 'MANUAL',
          createdAt: '2026-08-01T08:00:00+00:00',
          updatedAt: '2026-08-02T09:00:00+00:00',
        },
      ]),
    });
  });

  await page.route('**/api/applications/12/timeline', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        application: {
          id: 12,
          jobOfferId: 42,
          status: 'INTERVIEW',
          updatedAt: '2026-08-02T09:00:00+00:00',
        },
        events: [
          {
            id: 2,
            type: 'INTERVIEW_PROPOSED',
            label: 'Entretien proposé',
            occurredAt: '2026-08-02T09:00:00+00:00',
            recordedAt: '2026-08-02T09:00:01+00:00',
          },
          {
            id: 1,
            type: 'APPLICATION_SUBMITTED',
            label: 'Candidature envoyée',
            occurredAt: '2026-08-02T09:01:00+00:00',
            recordedAt: '2026-08-02T09:01:01+00:00',
          },
        ]),
      }),
    });
  });

  await page.goto('/parcours-candidatures');

  await expect(page.getByRole('heading', { name: 'Parcours des candidatures', level: 1 })).toBeVisible();
  await expect(page.getByRole('combobox', { name: 'Candidature' })).toHaveValue('12');
  await expect(page.getByText('2 événement(s) persisté(s)')).toBeVisible();
  await expect(page.getByText('Entretien proposé')).toBeVisible();
  await expect(page.getByText('Candidature envoyée')).toBeVisible();
  await expect(page.getByText('Candidature préparée')).toHaveCount(0);
  await expect(page.getByText(/Le statut courant reste visible comme contexte/)).toBeVisible();
});
