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
  receivedAt: '2026-08-14T10:15:00+02:00',
  category: 'INTERVIEW_REQUEST',
  classificationReason: 'Une invitation à un entretien ou à un rendez-vous a été détectée.',
  sourcePlatform: 'LinkedIn',
  actionRequired: true,
  processed: false,
  application: { id: 7, status: 'INTERVIEW' },
  jobOffer: { id: 12, title: 'Senior Symfony Developer', company: 'Example' },
  matchedAt: '2026-08-14T10:20:00+02:00',
  urgency: {
    level: 'URGENT',
    label: 'Urgent',
    actionRequired: true,
    reasons: ['Entretien ou rendez-vous à organiser.'],
    recommendedAction: 'Planifier ou répondre à l’entretien',
    ageHours: 2,
  },
};

const recruiterMessage = {
  ...interviewMessage,
  id: 43,
  gmailMessageId: 'gmail-message-43',
  threadId: 'gmail-thread-43',
  gmailUrl: 'https://mail.google.com/mail/u/0/#inbox/gmail-thread-43',
  subject: 'Mission Symfony pour septembre',
  snippet: 'Votre profil nous intéresse pour une mission Symfony.',
  bodyText: null,
  receivedAt: '2026-08-14T11:15:00+02:00',
  category: 'RECRUITER_OPPORTUNITY',
  classificationReason: 'Une proposition de poste ou de mission envoyée par un recruteur a été détectée.',
  application: null,
  jobOffer: null,
  matchedAt: null,
  urgency: {
    level: 'PRIORITY',
    label: 'Prioritaire',
    actionRequired: true,
    reasons: ['Proposition directe d’un recruteur.'],
    recommendedAction: 'Voir et répondre à la proposition',
    ageHours: 1,
  },
};

async function fulfillJson(route: Route, body: unknown, status = 200): Promise<void> {
  await route.fulfill({
    status,
    contentType: 'application/json',
    body: JSON.stringify(body),
  });
}

test('Gmail inbox prioritizes urgent messages and clears urgency after processing', async ({ page }) => {
  let processed = false;

  await page.route('**/api/integrations/gmail/status', async (route) => {
    await fulfillJson(route, {
      connected: true,
      readPermission: true,
      readPermissionMessage: null,
    });
  });

  await page.route('**/api/integrations/gmail/messages?limit=250', async (route) => {
    const currentInterview = processed
      ? {
          ...interviewMessage,
          processed: true,
          urgency: {
            level: 'NORMAL',
            label: 'Normal',
            actionRequired: false,
            reasons: [],
            recommendedAction: null,
            ageHours: 2,
          },
        }
      : interviewMessage;
    await fulfillJson(route, [recruiterMessage, currentInterview]);
  });

  await page.route('**/api/integrations/gmail/messages/*/processed', async (route) => {
    expect(route.request().method()).toBe('PATCH');
    processed = true;
    await fulfillJson(route, {
      ...interviewMessage,
      processed: true,
      urgency: {
        level: 'NORMAL',
        label: 'Normal',
        actionRequired: false,
        reasons: [],
        recommendedAction: null,
        ageHours: 2,
      },
    });
  });

  await page.goto('/messages');

  await expect(page.getByRole('heading', { name: 'Messagerie', level: 1 })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Synchroniser Gmail' })).toBeVisible();
  await expect(page.getByText('1 message(s) urgent(s) à traiter.')).toBeVisible();
  await expect(page.locator('span.badge').filter({ hasText: /^Urgent$/ })).toBeVisible();
  await expect(page.locator('span.badge').filter({ hasText: /^Prioritaire$/ })).toBeVisible();
  await expect(page.getByText('Planifier ou répondre à l’entretien.')).toBeVisible();
  await expect(page.getByText('Entretien ou rendez-vous à organiser.')).toBeVisible();
  await expect(page.getByText('LinkedIn', { exact: true }).first()).toBeVisible();
  await expect(page.getByRole('heading', { name: interviewMessage.subject, level: 3 })).toBeVisible();
  await expect(page.getByText(/Offre associée/)).toContainText('Senior Symfony Developer');
  await expect(page.getByText(/Candidature #7/)).toContainText('INTERVIEW');

  const subjects = page.getByRole('heading', { level: 3 });
  await expect(subjects.nth(0)).toHaveText(interviewMessage.subject);
  await expect(subjects.nth(1)).toHaveText(recruiterMessage.subject);

  await page.getByLabel('Afficher').selectOption('URGENT');
  await expect(page.getByRole('heading', { name: interviewMessage.subject, level: 3 })).toBeVisible();
  await expect(page.getByRole('heading', { name: recruiterMessage.subject, level: 3 })).not.toBeVisible();

  await page.getByRole('button', { name: 'Marquer l’entretien comme confirmé' }).click();
  await expect(page.getByRole('heading', { name: interviewMessage.subject, level: 3 })).not.toBeVisible();
  await expect(page.getByText('Aucun message ne correspond à ce filtre. Lance une synchronisation ou change le filtre.')).toBeVisible();
  expect(processed).toBe(true);
});

test('Gmail inbox remains usable on mobile with long content and keyboard-focusable details', async ({ page }) => {
  const longSubject = 'Invitation entretien technique Symfony avec un intitulé volontairement très long sans rupture facile pour vérifier le responsive mobile';
  const longMessage = {
    ...interviewMessage,
    subject: longSubject,
    sender: 'recrutement-international-technique-tres-long@example-consulting-company.test',
    sourcePlatform: 'Plateforme-de-recrutement-avec-un-nom-volontairement-tres-long',
  };

  await page.setViewportSize({ width: 390, height: 844 });
  await page.route('**/api/integrations/gmail/status', async (route) => {
    await fulfillJson(route, {
      connected: true,
      readPermission: true,
      readPermissionMessage: null,
    });
  });
  await page.route('**/api/integrations/gmail/messages?limit=250', async (route) => {
    await fulfillJson(route, [longMessage]);
  });

  await page.goto('/messages');

  await expect(page.getByRole('list', { name: 'Messages Gmail' })).toBeVisible();
  await expect(page.getByLabel('Afficher')).toBeVisible();
  await expect(page.getByRole('heading', { name: longSubject, level: 3 })).toBeVisible();

  const noHorizontalOverflow = await page.evaluate(
    () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
  );
  expect(noHorizontalOverflow).toBe(true);

  const detailsSummary = page.getByText('Afficher le contenu analysé', { exact: true });
  await detailsSummary.focus();
  await expect(detailsSummary).toBeFocused();

  const summaryBox = await detailsSummary.boundingBox();
  expect(summaryBox).not.toBeNull();
  expect(summaryBox?.height ?? 0).toBeGreaterThanOrEqual(44);

  await expect(page.getByRole('link', { name: 'Ouvrir dans Gmail' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Marquer l’entretien comme confirmé' })).toBeVisible();
});