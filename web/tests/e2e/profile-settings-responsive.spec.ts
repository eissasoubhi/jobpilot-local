import { expect, test, type Page } from '@playwright/test';

const routes = [
  ['/profil', 'Profil candidat'],
  ['/cv', 'Mes CV'],
  ['/parametres', 'Paramètres'],
] as const;

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
  await expect.poll(async () => page.evaluate(() => ({
    clientWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
  }))).toEqual(await page.evaluate(() => ({
    clientWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.clientWidth,
  })));
}

test.describe('Profile, CV and settings responsive contract', () => {
  test.use({ hasTouch: true });

  for (const viewport of [
    { name: 'tablet', width: 820, height: 1180 },
    { name: 'mobile', width: 390, height: 844 },
  ] as const) {
    test(`${viewport.name} layouts stay usable without horizontal overflow`, async ({ page }) => {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });

      for (const [route, heading] of routes) {
        await page.goto(route);
        await expect(page.getByRole('heading', { name: heading, level: 1 })).toBeVisible();
        await expectNoHorizontalOverflow(page);
      }
    });
  }

  test('mobile profile keeps labelled controls and touch targets usable', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/profil');

    const fullName = page.getByLabel('Nom complet');
    const save = page.getByRole('button', { name: 'Enregistrer' });

    await expect(fullName).toBeVisible();
    await expect(save).toBeVisible();
    await fullName.focus();
    await expect(fullName).toBeFocused();

    const inputBox = await fullName.boundingBox();
    const saveBox = await save.boundingBox();
    expect(inputBox?.height ?? 0).toBeGreaterThanOrEqual(44);
    expect(saveBox?.height ?? 0).toBeGreaterThanOrEqual(44);
  });
});
