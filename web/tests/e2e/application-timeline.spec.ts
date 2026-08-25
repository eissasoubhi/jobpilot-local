import { expect, test } from '@playwright/test';

test('application timeline shows only persisted business events for the selected offer', async ({ page }) => {
  await page.route('**/api/applications', async (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify([{
      id: 12,
      channel: 'Gmail automatique',
      status: 'INTERVIEW',
      updatedAt: '2026-08-04T12:00:00+00:00',
      message: '',
      coverLetter: '',
      jobOffer: {
        id: 30,
        title: 'Senior Symfony Developer',
        company: 'Acme',
        clientName: null,
        location: 'Paris',
        contractType: 'CDI',
        workMode: 'Hybride',
        language: 'fr',
        description: '',
        score: 88,
        scoreReasons: [],
        status: 'NEW',
        source: 'France Travail',
        sources: [],
        sourceCount: 1,
      },
    }]),
  }));

  await page.route('**/api/jobs/30/timeline', async (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify([
      {
        id: 15,
        jobOfferId: 30,
        applicationId: 12,
        type: 'INTERVIEW',
        source: 'gmail-inbox',
        payload: { category: 'INTERVIEW_REQUEST' },
        occurredAt: '2026-08-04T11:00:00+00:00',
        recordedAt: '2026-08-04T11:00:01+00:00',
      },
      {
        id: 14,
        jobOfferId: 30,
        applicationId: 12,
        type: 'APPLICATION_SUBMITTED',
        source: 'manual-status',
        payload: { previousStatus: 'DRAFT' },
        occurredAt: '2026-08-02T09:01:00+00:00',
        recordedAt: '2026-08-02T09:01:01+00:00',
      },
    ]),
  }));

  await page.goto('/parcours-candidatures');

  await expect(page.getByRole('heading', { name: 'Parcours des candidatures', level: 1 })).toBeVisible();
  await expect(page.getByLabel('Candidature')).toHaveValue('12');
  await expect(page.getByText('2 événement(s) persisté(s)')).toBeVisible();
  await expect(page.getByText('Entretien proposé')).toBeVisible();
  await expect(page.getByText('Candidature envoyée')).toBeVisible();
  await expect(page.getByText('Candidature préparée')).toHaveCount(0);
  await expect(page.getByText(/Le statut courant reste visible comme contexte/)).toBeVisible();
});
