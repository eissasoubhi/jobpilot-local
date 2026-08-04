import { expect, test, type Page, type Route } from '@playwright/test';

const settings = {
  interfaceLanguage: 'fr',
  targetJobs: ['Développeur Symfony'],
  exclusions: [],
  skills: ['PHP', 'Symfony'],
  matchingThreshold: 60,
  defaultIdfTjm: 500,
  defaultOutsideIdfTjm: 450,
  defaultRemoteTjm: 450,
  minimumFreelanceTjm: 400,
  maximumTjm: 520,
  minimumCdiSalary: 45000,
  salaryIncludesTotalCompensation: false,
  cddSalaryRule: null,
  autoPrepare: true,
  autoSubmitEnabled: false,
  autoSubmitThreshold: 60,
  autoSubmitDailyLimit: 5,
  finalSubmissionMode: 'ONE_CLICK',
};

const application = {
  id: 42,
  jobOffer: {
    id: 7,
    source: 'Test',
    title: 'Développeur Symfony Senior',
    company: 'Entreprise Test',
    location: 'Paris',
    contractType: 'Freelance',
    workMode: 'Hybride',
    language: 'fr',
    description: 'Mission Symfony.',
    score: 82,
    scoreReasons: [],
    status: 'PREPARED',
  },
  channel: 'Préparation locale',
  status: 'READY_TO_SUBMIT',
  cvDocument: {
    id: 3,
    name: 'CV Symfony',
    originalName: 'CV_Aissa_Symfony.pdf',
    language: 'fr',
    category: 'Symfony',
    tags: [],
    active: true,
    defaultForLanguage: true,
    size: 1234,
    downloadUrl: '/api/cvs/3/download',
  },
  message: 'Bonjour,\n\nLe poste correspond directement à mon parcours. Vous trouverez mon CV en pièce jointe.\n\nBien cordialement,\nAissa Soubhi',
  coverLetter: 'Madame, Monsieur,\n\nCette lettre reste séparée.',
  compensationAnswer: '500 € HT/jour',
  updatedAt: '2026-08-04T00:00:00+00:00',
};

const preview = {
  applicationId: 42,
  subject: 'Candidature – Développeur Symfony Senior',
  body: 'Bonjour,\n\nLe poste correspond directement à mon parcours. Vous trouverez mon CV en pièce jointe.\n\nConcernant la rémunération, ma proposition est de 500 € HT/jour.\n\nBien cordialement,\nAissa Soubhi',
  attachmentNames: ['CV_Aissa_Symfony.pdf'],
};

async function mockSettingsApis(
  page: Page,
  options: { sendPermission: boolean; onSend?: (payload: unknown) => void },
): Promise<void> {
  await page.route('**/api/**', async (route: Route) => {
    const request = route.request();
    const pathname = new URL(request.url()).pathname;

    if (pathname === '/api/settings/sources') {
      await route.fulfill({ json: [] });
      return;
    }

    if (pathname === '/api/settings') {
      await route.fulfill({ json: settings });
      return;
    }

    if (pathname === '/api/integrations/gmail/status') {
      await route.fulfill({
        json: {
          connected: true,
          sendPermission: options.sendPermission,
          sendPermissionMessage: options.sendPermission
            ? null
            : 'Le droit d’envoi Gmail n’est pas détecté.',
          configured: true,
          missingVariables: [],
          redirectUri: 'http://localhost:8080/api/integrations/gmail/callback',
          startUrl: 'http://localhost:8080/api/integrations/gmail/start',
        },
      });
      return;
    }

    if (pathname === '/api/applications') {
      await route.fulfill({ json: [application] });
      return;
    }

    if (pathname === '/api/integrations/gmail/test-preview/42') {
      await route.fulfill({ json: preview });
      return;
    }

    if (pathname === '/api/integrations/gmail/test-send' && request.method() === 'POST') {
      const payload = request.postDataJSON();
      options.onSend?.(payload);
      await route.fulfill({
        status: 201,
        json: {
          ...preview,
          sent: true,
          recipient: 'destination@example.com',
          gmailMessageId: 'gmail-message-123',
          applicationStatusChanged: false,
          dailyLimitConsumed: false,
        },
      });
      return;
    }

    await route.fulfill({ status: 404, json: { error: `Route non simulée : ${pathname}` } });
  });
}

test('sends one concise automatic email without concatenating the cover letter', async ({ page }) => {
  let submittedPayload: unknown = null;
  await mockSettingsApis(page, {
    sendPermission: true,
    onSend: (payload) => {
      submittedPayload = payload;
    },
  });
  page.on('dialog', async (dialog) => {
    expect(dialog.message()).toContain('destination@example.com');
    await dialog.accept();
  });

  await page.goto('/parametres');

  await expect(page.getByRole('heading', { name: 'Tester l’envoi automatique' })).toBeVisible();
  await expect(page.getByLabel('Sujet exact')).toHaveValue(preview.subject);
  await expect(page.getByLabel('Corps exact reçu par le destinataire')).toHaveValue(preview.body);
  await expect(page.getByLabel('Corps exact reçu par le destinataire')).not.toHaveValue(/---/);
  await expect(page.getByLabel('Corps exact reçu par le destinataire')).not.toHaveValue(/Cette lettre reste séparée/);
  await expect(page.getByText('CV_Aissa_Symfony.pdf')).toBeVisible();

  await page.getByLabel('Adresse e-mail de destination').fill('destination@example.com');
  const sendButton = page.getByRole('button', { name: 'Envoyer le mail de test' });
  await expect(sendButton).toBeEnabled();
  await sendButton.click();

  await expect(page.getByText(/Mail de test envoyé à destination@example.com/)).toBeVisible();
  expect(submittedPayload).toEqual({
    recipient: 'destination@example.com',
    applicationId: 42,
  });
});

test('explains why the Gmail test button is disabled without gmail.send', async ({ page }) => {
  await mockSettingsApis(page, { sendPermission: false });

  await page.goto('/parametres');
  await page.getByLabel('Adresse e-mail de destination').fill('destination@example.com');

  await expect(page.getByTestId('email-test-blocker')).toContainText(
    'Le droit d’envoi Gmail n’est pas détecté.',
  );
  await expect(page.getByRole('button', { name: 'Envoyer le mail de test' })).toBeDisabled();
});
