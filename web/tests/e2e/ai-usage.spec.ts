import { expect, test } from '@playwright/test';

test('AI usage dashboard exposes local quota and billing boundaries', async ({ page }) => {
  await page.goto('/ia');

  await expect(page.getByRole('heading', { name: 'Utilisation IA' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Quotas de sécurité JobPilot' })).toBeVisible();
  await expect(page.getByText('Ce chiffre est une estimation JobPilot, pas le solde officiel Google AI Studio.')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Calendrier d’utilisation' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Opérations récentes' })).toBeVisible();
});

test('AI usage dashboard stays usable on a mobile viewport', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/ia');

  await expect(page.getByRole('heading', { name: 'Utilisation IA' })).toBeVisible();
  const hasHorizontalPageOverflow = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  );
  expect(hasHorizontalPageOverflow).toBe(false);

  await page.getByRole('button', { name: 'Menu' }).click();
  await expect(page.getByRole('link', { name: /Utilisation IA/ })).toBeVisible();
});
