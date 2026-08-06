import { expect, test } from '@playwright/test';

test('application timeline shows stored application and associated Gmail events', async ({ page }) => {
  await page.route('**/api/applications', async (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify([{
      id: 12,
      channel: 'Gmail automatique',
      status: 'INTERVIEW',
      createdAt: '2026-08-01T08:00:00+00:00',
      updatedAt: '2026-08-04T12:00:00+00:00',
      submittedAt: '2026-08-02T09:01:00+00:00',
      submissionAttemptedAt: '2026-08-02T09:00:00+00:00',
      submissionError: null,
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

  await page.route('**/api/integrations/gmail/messages', async (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify([{
      id: 90,
      gmailMessageId: 'gmail-90',
      threadId: 'thread-90',
      gmailUrl: 'https://mail.google.com/mail/u/0/#inbox/90',
      sender: 'recruteur@acme.test',
      recipient: 'candidate@test.local',
      replyTo: null,
      subject: 'Entretien jeudi',
      snippet: 'Disponible jeudi ?',
      bodyText: null,
      receivedAt: '2026-08-04T11:00:00+00:00',
      category: 'INTERVIEW_REQUEST',
      classificationReason: 'Invitation explicite',
      sourcePlatform: null,
      actionRequired: true,
      processed: false,
      application: { id: 12, status: 'INTERVIEW' },
      jobOffer: { id: 30, title: 'Senior Symfony Developer', company: 'Acme' },
      matchedAt: '2026-08-04T11:01:00+00:00',
    }]),
  }));

  await page.goto('/parcours-candidatures');

  await expect(page.getByRole('heading', { name: 'Parcours des candidatures', level: 1 })).toBeVisible();
  await expect(page.getByLabel('Candidature')).toHaveValue('12');
  await expect(page.getByText('Invitation à un entretien reçue')).toBeVisible();
  await expect(page.getByText(/Entretien jeudi.*recruteur@acme.test.*action requise/)).toBeVisible();
  await expect(page.getByText('Candidature envoyée')).toBeVisible();
  await expect(page.getByText('Candidature préparée')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Ouvrir dans Gmail' })).toHaveAttribute('href', 'https://mail.google.com/mail/u/0/#inbox/90');
});
