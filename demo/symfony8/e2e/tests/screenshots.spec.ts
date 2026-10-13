import { test, expect, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 — demo screenshots (PNG captures, converted to WebP by scripts/to-webp.mjs).
 *
 * - overview: home page with the consent banner (demo default profile: page overlay on)
 * - interaction: preferences step with the Analytics category switched on and expanded
 * - cookie-consent-modal-overlay / cookie-consent-modal-default: preferences step with and
 *   without the "Page overlay (disable interaction)" profile setting
 */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

/** Settings page of the default demo profile (id 1, seeded by demo:seed-cookie-consent-config). */
const PROFILE_SETTINGS = '/en/demo/admin/cookie-consent-config/1/settings';
const OVERLAY_CHECKBOX = '#cookie_consent_config_settings_disablePageInteraction';

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

async function openHome(page: Page) {
  await page.context().clearCookies();
  await page.goto('/en/');
  const host = page.locator('.nowo-cookie-consent').first();
  await expect(host.locator('[data-nowo-step="banner"]')).toBeVisible({ timeout: 10000 });
  return host;
}

async function openPreferences(page: Page) {
  const host = await openHome(page);
  await host.locator('[data-nowo-show-preferences]').first().click();
  await expect(host.locator('[data-nowo-step="preferences"]')).toBeVisible({ timeout: 5000 });
  await page.waitForTimeout(400); // let the step transition settle
  return host;
}

/** Sets the overlay checkbox of the default profile through the demo admin form. */
async function setOverlay(page: Page, enabled: boolean) {
  await page.context().clearCookies();
  await page.goto(PROFILE_SETTINGS);
  const checkbox = page.locator(OVERLAY_CHECKBOX);
  await expect(checkbox).toBeAttached();
  // The consent modal (with overlay) covers the admin page: drive the form via the DOM.
  const submitted = page.waitForResponse(
    (r) => r.request().method() === 'POST' && r.url().includes('/settings'),
  );
  await page.evaluate(
    ({ selector, value }) => {
      const input = document.querySelector<HTMLInputElement>(selector);
      if (!input || !input.form) {
        throw new Error('overlay checkbox not found');
      }
      input.checked = value;
      input.form.requestSubmit();
    },
    { selector: OVERLAY_CHECKBOX, value: enabled },
  );
  await submitted;
  await page.waitForLoadState('load');
  await page.goto(PROFILE_SETTINGS);
  if (enabled) {
    await expect(page.locator(OVERLAY_CHECKBOX)).toBeChecked();
  } else {
    await expect(page.locator(OVERLAY_CHECKBOX)).not.toBeChecked();
  }
}

test.describe.serial('CookieConsent screenshots', () => {
  // Start from the demo preset (overlay on); demo PHPUnit tests share the dev database.
  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    await setOverlay(page, true);
    await page.close();
  });

  test('overview — consent banner on the demo home', async ({ page }) => {
    await openHome(page);
    await page.waitForTimeout(400);
    await page.screenshot({ path: resolve(outDir, 'overview.png') });
  });

  test('interaction — preferences step with Analytics enabled and expanded', async ({ page }) => {
    const host = await openPreferences(page);
    const analytics = host.locator('#cookie_consent_analytics');
    if (await analytics.count()) {
      await analytics.check({ force: true });
      await expect(analytics).toBeChecked();
    }
    // Expand the Analytics category to show its description and cookie inventory.
    const details = host.locator('[data-nowo-category="analytics"] details').first();
    if (await details.count()) {
      await details.evaluate((el) => {
        (el as HTMLDetailsElement).open = true;
      });
      await details.scrollIntoViewIfNeeded();
    }
    await page.waitForTimeout(800); // toggle knob transition
    await page.screenshot({ path: resolve(outDir, 'interaction.png') });
  });

  test('modal with page overlay (disable_page_interaction on)', async ({ page }) => {
    await setOverlay(page, true);
    const host = await openPreferences(page);
    await expect(host).toHaveAttribute('data-nowo-disable-page-interaction', 'true');
    await page.screenshot({ path: resolve(outDir, 'cookie-consent-modal-overlay.png') });
  });

  test('modal without page overlay (default)', async ({ page }) => {
    await setOverlay(page, false);
    try {
      const host = await openPreferences(page);
      await expect(host).toHaveAttribute('data-nowo-disable-page-interaction', 'false');
      await page.screenshot({ path: resolve(outDir, 'cookie-consent-modal-default.png') });
    } finally {
      await setOverlay(page, true); // restore the demo preset
    }
  });
});
