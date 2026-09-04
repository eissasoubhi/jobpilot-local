import { expect, test, type Page } from '@playwright/test';

const routes = [
  ['/offres/review', 'Review Queue'],
  ['/offres', 'Offres'],
  ['/candidatures', 'Candidatures'],
  ['/crm', 'CRM'],
  ['/messages', 'Messagerie'],
  ['/reporting', 'Reporting candidatures'],
  ['/reporting/sources', 'Conversion'],
  ['/connecteurs', 'Connecteurs'],
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

test.describe('V1 responsive contract', () => {
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

  test('mobile connectors keeps the primary refresh action keyboard and touch usable', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/connecteurs');

    const refresh = page.getByRole('button', { name: 'Actualiser' });
    await expect(refresh).toBeVisible();
    await refresh.focus();
    await expect(refresh).toBeFocused();

    const refreshBox = await refresh.boundingBox();
    expect(refreshBox).not.toBeNull();
    expect(refreshBox?.height ?? 0).toBeGreaterThanOrEqual(44);
  });
});
