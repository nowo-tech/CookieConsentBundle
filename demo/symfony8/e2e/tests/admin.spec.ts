import { test, expect } from '@playwright/test';

/**
 * Bundle admin pages (REQ-UI-003): UiKit page scaffolds inside the demo host layout (nowo_ui_kit.layouts.admin).
 * Default demo profile: id 1 (demo:seed-cookie-consent-config).
 */
const COOKIES = '/cookie-consent-config/1/cookies';
const SETTINGS = '/cookie-consent-config/1/settings/profile';

test.describe('CookieConsent bundle admin (UiKit page contract)', () => {
  test('cookie inventory is a UiKit list page without the consent banner', async ({ page }) => {
    const response = await page.goto(COOKIES);
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('[data-nowo-ui-page="list"]')).toBeVisible();
    await expect(page.locator('[data-nowo-ui-region="area-nav"] a[aria-current="page"]')).toHaveAttribute('href', COOKIES);
    // UiKit 2.1 area navigation: inside the page header, 1.x data-turbo="false" kept on the <nav>.
    await expect(page.locator('[data-nowo-ui-region="header"] [data-nowo-ui-region="area-nav"]')).toHaveAttribute('data-turbo', 'false');
    await expect(page.locator('[data-nowo-ui-region="table"] tbody tr').first()).toBeVisible();
    await expect(page.locator('.nowo-cookie-consent')).toHaveCount(0);
  });

  test('delete opens the UiKit confirm dialog and cancel closes it', async ({ page }) => {
    await page.goto(COOKIES);
    const row = page.locator('[data-nowo-ui-region="table"] tbody tr').first();
    await row.locator('[data-nowo-confirm-open], [data-nowo-confirm-target]').first().click();
    const dialog = page.locator('dialog[data-nowo-ui-confirm][open]');
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('form[name^="delete_cookie_definition_"]')).toHaveCount(1);
    await dialog.locator('[data-nowo-confirm-close]').first().click();
    await expect(page.locator('dialog[data-nowo-ui-confirm][open]')).toHaveCount(0);
  });

  test('new cookie form and settings section tabs', async ({ page }) => {
    await page.goto(COOKIES);
    await page.locator('[data-nowo-ui-region="header"] a[href$="/cookies/new"]').first().click();
    await expect(page.locator('[data-nowo-ui-page="form"] form[name="cookie_definition"]')).toBeVisible();

    await page.goto(SETTINGS);
    await expect(page.locator('[data-nowo-ui-page="settings"]')).toBeVisible();
    await page.locator('[data-nowo-ui-region="tabs"] a[href$="/settings/appearance"]').click();
    await expect(page).toHaveURL(/\/settings\/appearance$/);
    await expect(page.locator('[data-nowo-ui-region="tabs"] a[aria-current="page"]')).toHaveAttribute('href', /\/settings\/appearance$/);
  });
});
