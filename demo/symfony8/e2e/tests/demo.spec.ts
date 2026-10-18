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

  test('dark-turquoise preferences: readable category headers, footer on the modal surface, accent save button', async ({
    page,
  }) => {
    await page.context().clearCookies();
    await page.goto('/en/');
    const host = page.locator('.nowo-cookie-consent').first();
    await expect(host).toHaveClass(/nowo-cookie-consent--theme-dark-turquoise/, { timeout: 10000 });
    await host.locator('[data-nowo-show-preferences]').first().click();
    const step = host.locator('[data-nowo-step="preferences"]');
    await expect(step).toBeVisible({ timeout: 5000 });

    const report = await step.evaluate((root) => {
      // Chromium serialises color-mix() results as color(srgb r g b [/ a]); plain colours as rgb()/rgba().
      const parse = (value: string): number[] => {
        const srgb = value.match(/color\(srgb ([\d.e-]+) ([\d.e-]+) ([\d.e-]+)(?: \/ ([\d.]+))?\)/);
        if (srgb) {
          return [Number(srgb[1]) * 255, Number(srgb[2]) * 255, Number(srgb[3]) * 255, srgb[4] ? Number(srgb[4]) : 1];
        }
        const rgb = value.match(/rgba?\(([^)]+)\)/);
        if (!rgb) {
          throw new Error(`unparsed colour ${value}`);
        }
        const parts = rgb[1].split(/[\s,/]+/).filter(Boolean).map(Number);
        return [parts[0], parts[1], parts[2], parts[3] ?? 1];
      };
      const over = (top: number[], bottom: number[]) => [0, 1, 2].map((i) => top[i] * top[3] + bottom[i] * (1 - top[3]));
      const lum = (c: number[]) =>
        c
          .slice(0, 3)
          .map((v) => v / 255)
          .map((s) => (s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4))
          .reduce((sum, v, i) => sum + v * [0.2126, 0.7152, 0.0722][i], 0);
      const ratio = (a: number[], b: number[]) => {
        const [hi, lo] = [lum(a), lum(b)].sort((x, y) => y - x);
        return (hi + 0.05) / (lo + 0.05);
      };
      const panel = root.closest('.modal-content, .nowo-cookie-consent__panel') as HTMLElement;
      const surface = parse(getComputedStyle(panel).backgroundColor);
      const headers = [...root.querySelectorAll<HTMLElement>('.nowo-cookie-consent__category-title-row')].map((row) => {
        const title = row.querySelector<HTMLElement>('.nowo-cookie-consent__category-title') ?? row;
        const bg = over(parse(getComputedStyle(row).backgroundColor), surface);
        return { title: title.textContent?.trim(), contrast: ratio(parse(getComputedStyle(title).color), bg) };
      });
      const footer = root.querySelector<HTMLElement>('.modal-footer, .nowo-cookie-consent__section-footer') as HTMLElement;
      const save = footer.querySelector<HTMLElement>('button') as HTMLElement;
      return {
        headers,
        footerBg: getComputedStyle(footer).backgroundColor,
        panelBg: getComputedStyle(panel).backgroundColor,
        saveBg: getComputedStyle(save).backgroundColor,
        saveContrast: ratio(parse(getComputedStyle(save).color), parse(getComputedStyle(save).backgroundColor)),
      };
    });

    expect(report.headers.length).toBeGreaterThanOrEqual(4);
    for (const header of report.headers) {
      expect(header.contrast, `contrast of the "${header.title}" header`).toBeGreaterThanOrEqual(4.5);
    }
    expect(report.footerBg).toBe(report.panelBg);
    expect(report.saveBg).toBe('rgb(96, 254, 210)'); // dark-turquoise accent
    expect(report.saveContrast).toBeGreaterThanOrEqual(4.5);
  });
});
