import { test, expect, type Browser, type BrowserContext, type Page } from '@playwright/test';

/**
 * Owner decision JH-L1 (1.25): changing the profile revision asks the admin whether visitors must consent again.
 * "No" keeps saved consents; "Yes" re-opens the banner for everyone who consented before, with only necessary cookies
 * applying and nothing optional pre-ticked until they answer. Runs in Chromium and WebKit.
 *
 * The admin works in its own browser context, so the visitor's cookies are only touched by the visitor's own pages.
 * Default demo profile: id 1 (demo:seed-cookie-consent-config).
 */
const HOME = '/en/';
const BEHAVIOR = '/cookie-consent-config/1/settings/behavior';
const BANNER = '#cookieconsent';

/**
 * WebKit (Playwright on Linux) drops `Secure` cookies on http://127.0.0.1, and the bundle always sets `Secure`. There,
 * mirror what the server sets or expires into the context without the flag, so the flow can run over plain HTTP.
 */
function mirrorConsentCookies(page: Page, browserName: string): void {
  if (browserName !== 'webkit') {
    return;
  }
  page.on('response', async (response) => {
    const url = new URL(response.url());
    for (const header of (await response.headersArray()).filter((h) => h.name.toLowerCase() === 'set-cookie')) {
      for (const line of header.value.split('\n')) {
        const [pair, ...attributes] = line.split(';');
        const eq = pair.indexOf('=');
        const name = pair.slice(0, eq).trim();
        if (!name.startsWith('Cookie_')) {
          continue;
        }
        const value = decodeURIComponent(pair.slice(eq + 1).trim());
        const expired = attributes.some((a) => /max-age=0\b/i.test(a.trim())) || value === '' || value === 'deleted';
        if (expired) {
          await page.context().clearCookies({ name });
          continue;
        }
        await page.context().addCookies([{ name, value, domain: url.hostname, path: '/', secure: false, sameSite: 'Lax' }]);
      }
    }
  });
}

async function consentAll(page: Page): Promise<void> {
  await page.goto(HOME);
  const modal = page.locator(BANNER);
  await expect(modal).toHaveAttribute('data-nowo-open', 'true');
  await modal.locator('button[name="cookie_consent[use_all_cookies]"]').first().click();
  await expect(page.locator('html')).not.toHaveClass(/nowo-cookie-consent-open/, { timeout: 10000 });
}

async function cookieNames(context: BrowserContext): Promise<string[]> {
  return (await context.cookies()).map((cookie) => cookie.name);
}

/** Saves the behavior settings with the revision raised by one; answer null leaves the question unanswered. */
async function raiseRevision(browser: Browser, answer: 'yes' | 'no' | null): Promise<Page> {
  const admin = await (await browser.newContext()).newPage();
  await admin.goto(BEHAVIOR);
  const revision = admin.locator('input[name$="[revision]"]');
  const current = Number(await revision.inputValue());
  await revision.fill(String(current + 1));
  const radios = admin.locator('input[type="radio"][name$="[reconsent]"]');
  await expect(radios).toHaveCount(2);
  for (const radio of await radios.all()) {
    await expect(radio).not.toBeChecked();
  }
  if (answer !== null) {
    await admin.locator(`input[type="radio"][name$="[reconsent]"][value="${answer}"]`).check();
  }
  // Refused: the POST answers 422 in place. Saved: the POST redirects back to the section (GET).
  const done = admin.waitForResponse((response) =>
    answer === null
      ? response.request().method() === 'POST' && response.status() === 422
      : response.request().method() === 'GET' && response.url().endsWith(BEHAVIOR),
  );
  await revision.evaluate((input) => (input as HTMLInputElement).form?.requestSubmit());
  await done;
  await admin.waitForLoadState('load');

  return admin;
}

test.describe('Re-consent after a revision change (JH-L1)', () => {
  test('a revision change without an answer is refused', async ({ browser }) => {
    const admin = await raiseRevision(browser, null);
    await expect(admin.getByText('You changed the revision: choose whether visitors must consent again.')).toBeVisible();
    await admin.context().close();
  });

  test('"No" keeps saved consents', async ({ page, browser, browserName }) => {
    mirrorConsentCookies(page, browserName);
    await page.context().clearCookies();
    await consentAll(page);
    // Consent times have second precision: make sure the admin change happens in a later second.
    await page.waitForTimeout(1100);

    const admin = await raiseRevision(browser, 'no');
    await expect(admin.locator('input[name$="[revision]"]')).toBeVisible();
    await admin.context().close();

    await page.goto(HOME);
    await expect(page.locator(BANNER)).toHaveAttribute('data-nowo-open', 'false');
    expect(await cookieNames(page.context())).toContain('Cookie_Category_analytics');
  });

  test('"Yes" asks visitors again, only necessary cookies until they answer, then the new answer sticks', async ({
    page,
    browser,
    browserName,
  }) => {
    mirrorConsentCookies(page, browserName);
    await page.context().clearCookies();
    await consentAll(page);
    expect(await cookieNames(page.context())).toContain('Cookie_Category_analytics');
    await page.waitForTimeout(1100);

    const admin = await raiseRevision(browser, 'yes');
    await expect(admin.locator('input[name$="[revision]"]')).toBeVisible();
    await admin.context().close();

    // The old consent no longer counts: banner open again, optional consent cookies expired, key kept.
    await page.goto(HOME);
    const modal = page.locator(BANNER);
    await expect(modal).toHaveAttribute('data-nowo-open', 'true');
    await expect(modal).toHaveAttribute('data-nowo-reconsent-required-at', /^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/);
    await expect(page.locator('html')).toHaveClass(/nowo-cookie-consent-open/);
    const names = await cookieNames(page.context());
    expect(names).not.toContain('Cookie_Consent');
    expect(names.filter((name) => name.startsWith('Cookie_Category_'))).toEqual([]);
    expect(names).toContain('Cookie_Consent_Key');

    // Earlier choices are not pre-ticked.
    const prefs = modal.locator('[data-nowo-show-preferences]').first();
    if (await prefs.count()) {
      await prefs.click();
    }
    for (const toggle of await modal.locator('input[type="checkbox"]:not([disabled])').all()) {
      await expect(toggle).not.toBeChecked();
    }

    // The visitor answers again (Save with nothing ticked = necessary only): the banner closes and stays closed.
    await modal.locator('button[name="cookie_consent[save]"]:visible').first().click();
    await expect(page.locator('html')).not.toHaveClass(/nowo-cookie-consent-open/, { timeout: 10000 });
    const stored = await page.context().cookies();
    expect(stored.find((cookie) => cookie.name === 'Cookie_Consent')).toBeTruthy();
    expect(stored.find((cookie) => cookie.name === 'Cookie_Category_analytics')?.value).toBe('false');

    await page.goto(HOME);
    await expect(page.locator(BANNER)).toHaveAttribute('data-nowo-open', 'false');
  });
});
