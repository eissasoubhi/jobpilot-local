import { expect, test } from '@playwright/test';

test('shows application conversion reporting from stored applications', async ({ page }) => {
  await page.route('**/api/applications', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify([
        {
          id: 1,
          channel: 'EMAIL',
          status: 'INTERVIEW',
          submittedAt: '2026-08-01T10:00:00+00:00',
          updatedAt: '2026-08-05T10:00:00+00:00',
          message: '',
          coverLetter: '',
          jobOffer: {
            id: 1,
            source: 'France Travail',
            title: 'Développeur Symfony',
            company: 'Example',
            sources: [],
            sourceCount: 1,
            location: 'Paris',
            contractType: 'CDI',
            workMode: 'Hybride',
            language: 'fr',
            description: '',
            score: 90,
            scoreReasons: [],
            status: 'NEW',
          },
        },
      ]),
    });
  });

  await page.goto('/reporting');
  await expect(page.getByRole('heading', { name: 'Reporting candidatures' })).toBeVisible();
  await expect(page.getByText('1 préparée(s)')).toBeVisible();
  await expect(page.getByText('1 entretien(s)').first()).toBeVisible();
  await expect(page.getByText('France Travail')).toBeVisible();
});
