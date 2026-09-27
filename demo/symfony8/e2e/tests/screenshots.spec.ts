import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/** REQ-DEMO-013 — crop to .nowo-cookie-consent banner / preferences. */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('CookieConsent screenshots (functionality only)', () => {
  test('overview — banner', async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/en/');
    const host = page.locator('.nowo-cookie-consent').first();
    await expect(host).toBeVisible({ timeout: 10000 });
    await host.screenshot({ path: resolve(outDir, 'overview.png') });
  });

  test('interaction — preferences', async ({ page }) => {
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
    await host.screenshot({ path: resolve(outDir, 'interaction.png') });
  });
});
