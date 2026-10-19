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

  test('cookie inventory: one-line row actions, short columns on one line, purpose clamped with its full text', async ({ page }) => {
    await page.goto(COOKIES);
    const row = page.locator('[data-nowo-ui-region="table"] tbody tr').first();
    const actions = row.locator('.nowo-ui-row-actions > *');
    await expect(actions).toHaveCount(2);
    const [edit, del] = [await actions.nth(0).boundingBox(), await actions.nth(1).boundingBox()];
    expect(Math.abs((edit?.y ?? 0) - (del?.y ?? 100))).toBeLessThan(2);
    const purpose = row.locator('.nowo-cc-admin-clamp');
    await expect(purpose).toHaveAttribute('title', /\S/);
    const lines = await purpose.evaluate((el) => el.getBoundingClientRect().height / parseFloat(getComputedStyle(el).lineHeight));
    expect(lines).toBeLessThanOrEqual(2.05);
    await expect(page.locator('[data-nowo-ui-region="table"] tbody tr')).toHaveCount(10); // demo list_page_size
  });

  test('cookie definition form: field grid, one translation tab per enabled locale, save round-trip keeps CSRF and validation', async ({
    page,
  }) => {
    await page.goto(COOKIES);
    await page.locator('[data-nowo-ui-region="table"] tbody tr').first().locator('a.nowo-ui-action--edit').click();
    const form = page.locator('[data-nowo-ui-page="form"] form[name="cookie_definition"]');
    await expect(form).toBeVisible();

    const grid = form.locator('[data-nowo-cc-region="definition-fields"]');
    const [name, duration] = [await grid.locator('#cookie_definition_name').boundingBox(), await grid.locator('#cookie_definition_duration').boundingBox()];
    expect(Math.abs((name?.y ?? 0) - (duration?.y ?? 100))).toBeLessThan(2); // two columns
    expect(Math.abs((name?.width ?? 0) - (duration?.width ?? 0))).toBeLessThan(2); // same width

    // 1.18: fixed locale tabs (UiKit locale_tabs), the default locale first; the locale is a hidden input.
    const tabs = form.locator('[data-testid="nowo-cc-definition-translations"]');
    await expect(tabs.locator('[data-nowo-ui-tabs-ready]')).toHaveCount(1);
    await expect(tabs.locator('[role="tab"]').first()).toHaveAttribute('data-locale', 'en');
    await expect(form.locator('input[name="cookie_definition[translations][en][locale]"]')).toHaveAttribute('type', 'hidden');
    const provider = form.locator('input[name="cookie_definition[translations][en][provider]"]');
    const original = await provider.inputValue();

    // Server-side validation (browser validation off): a too long provider in another tab is rejected (422); that tab
    // opens and is marked invalid.
    const editUrl = page.url();
    await form.evaluate((el) => ((el as HTMLFormElement).noValidate = true));
    await page.locator('#tab-nowo-cc-definition-translations-es').click();
    await form.locator('input[name="cookie_definition[translations][es][provider]"]').fill('p'.repeat(121));
    await page.locator('#tab-nowo-cc-definition-translations-en').click();
    await form.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(editUrl);
    await expect(page.locator('#tab-nowo-cc-definition-translations-es')).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('#tab-nowo-cc-definition-translations-es')).toHaveAttribute('data-invalid', '');
    await expect(page.locator('input[name="cookie_definition[translations][es][provider]"]')).toBeVisible();

    await page.goto(editUrl);
    await page.locator('input[name="cookie_definition[translations][en][provider]"]').fill(`${original} (e2e)`);
    await form.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/cookies$/);
    await expect(page.locator('[data-nowo-ui-region="table"]')).toContainText(`${original} (e2e)`);

    // Restore the seeded value.
    await page.locator('[data-nowo-ui-region="table"] tbody tr').first().locator('a.nowo-ui-action--edit').click();
    await page.locator('input[name="cookie_definition[translations][en][provider]"]').fill(original);
    await page.locator('form[name="cookie_definition"] button[type="submit"]').click();
    await expect(page).toHaveURL(/\/cookies$/);
  });
});
