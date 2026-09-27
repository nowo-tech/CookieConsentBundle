import { test, expect } from '@playwright/test';

test.describe('CookieConsent demo', () => {
  test('home shows consent banner', async ({ page }) => {
    await page.context().clearCookies();
    const response = await page.goto('/en/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('.nowo-cookie-consent').first()).toBeVisible({ timeout: 10000 });
  });

  test('preferences step opens', async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/en/');
    const host = page.locator('.nowo-cookie-consent').first();
    await expect(host).toBeVisible({ timeout: 10000 });
    const prefs = host.locator('[data-nowo-show-preferences]').first();
    if (await prefs.count()) {
      await prefs.click();
    }
    await expect(
      host.locator('[data-nowo-step="preferences"], [data-nowo-step="prefs"]').first(),
    ).toBeVisible({ timeout: 5000 });
  });
});
