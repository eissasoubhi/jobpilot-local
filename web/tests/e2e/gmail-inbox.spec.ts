import { expect, test, type Route } from '@playwright/test';

const interviewMessage = {
  id: 42,
  gmailMessageId: 'gmail-message-42',
  threadId: 'gmail-thread-42',
  gmailUrl: 'https://mail.google.com/mail/u/0/#inbox/gmail-thread-42',
  sender: 'Recruiter <recruiter@example.com>',
  recipient: 'aissa@example.com',
  replyTo: 'recruiter@example.com',
  subject: 'Invitation entretien technique Symfony',
  snippet: 'Votre profil nous intéresse. Choisissez un créneau.',
  bodyText: 'Bonjour, votre profil Symfony nous intéresse. Choisissez un créneau Calendly.',
  receivedAt: '2026-08-05T00:15:00+02:00',
  category: 'INTERVIEW_REQUEST',
  classificationReason: 'Une invitation à un entretien ou à un rendez-vous a été détectée.',
  sourcePlatform: 'LinkedIn',
  actionRequired: true,
  processed: false,
  application: { id: 7, status: 'INTERVIEW' },
  jobOffer: { id: 12, title: 'Senior Symfony Developer', company: 'Example' },
  matchedAt: '2026-08-05T00:20:00+02:00',
};

async function fulfillJson(route: Route, body: unknown, status = 200): Promise<void> {
  await route.fulfill({
    status,
    contentType: 'application/json',
    body: JSON.stringify(body),
  });
}

test('Gmail inbox exposes classification, association, filters and processing', async ({ page }) => {
  let processed = false;

  await page.route('**/api/integrations/gmail/status', async (route) => {
    await fulfillJson(route, {
      connected: true,
      readPermission: true,
      readPermissionMessage: null,
    });
  });

  await page.route('**/api/integrations/gmail/messages**', async (route) => {
    if (route.request().method() === 'PATCH') {
      processed = true;
      await fulfillJson(route, { ...interviewMessage, processed: true });
      return;
    }

    const url = new URL(route.request().url());
    expect(url.searchParams.get('actionRequired') ?? 'true').toBe('true');
    await fulfillJson(route, [{ ...interviewMessage, processed }]);
  });

  await page.goto('/messages');

  await expect(page.getByRole('heading', { name: 'Messagerie', level: 1 })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Synchroniser Gmail' })).toBeVisible();
  await expect(page.locator('span.badge').filter({ hasText: /^Entretien$/ })).toBeVisible();
  await expect(page.getByText('LinkedIn', { exact: true })).toBeVisible();
  await expect(page.getByText('Action requise', { exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: interviewMessage.subject, level: 3 })).toBeVisible();
  await expect(page.getByText(/Offre associée/)).toContainText('Senior Symfony Developer');
  await expect(page.getByText(/Candidature #7/)).toContainText('INTERVIEW');
  await expect(page.getByRole('link', { name: 'Ouvrir dans Gmail' })).toHaveAttribute('href', interviewMessage.gmailUrl);

  await page.getByLabel('Afficher').selectOption('ACTION_REQUIRED');
  await expect(page.getByRole('heading', { name: interviewMessage.subject, level: 3 })).toBeVisible();

  await page.getByRole('button', { name: 'Marquer comme traité' }).click();
  await expect(page.getByText('Traité', { exact: true })).toBeVisible();
  expect(processed).toBe(true);
});
