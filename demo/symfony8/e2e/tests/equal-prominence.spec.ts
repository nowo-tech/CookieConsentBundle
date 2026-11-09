import { test, expect, type Page } from '@playwright/test';

/**
 * Owner decision JD-L3 (1.23, "Both filled the same"), audit JD-M1: with equal-weight buttons the real reject and accept
 * buttons of the demo page (`name="cookie_consent[…]"`) have identical computed styles and size, at rest and on hover,
 * in every colour theme, with and without a brand primary, at 390px and 1280px.
 * Runs on the Bootstrap demo (make test-e2e) and on the Tailwind demo with DEMO_BASE_URL pointing at it
 * (demo/symfony8-tailwind, port 8015), in Chromium and WebKit (project `prominence-webkit`).
 */
const THEMES = [
  '',
  'nowo-cookie-consent--theme-light',
  'nowo-cookie-consent--theme-dark',
  'nowo-cookie-consent--theme-light nowo-cookie-consent--dark-mode',
  'nowo-cookie-consent--theme-dark-turquoise',
  'nowo-cookie-consent--theme-light-funky',
  'nowo-cookie-consent--theme-elegant-black',
];
const VIEWPORTS = [
  { width: 390, height: 844 },
  { width: 1280, height: 900 },
];
const PROPERTIES = [
  'background-color',
  'background-image',
  'color',
  'border-top-color',
  'border-top-width',
  'border-top-style',
  'border-radius',
  'box-shadow',
  'font-weight',
  'font-size',
  'font-family',
  'outline-color',
  'outline-style',
];

const REJECT = '#cookieconsent [name="cookie_consent[use_only_functional_cookies]"]';
const ACCEPT = '#cookieconsent [name="cookie_consent[use_all_cookies]"]';

async function visible(page: Page, selector: string): Promise<string> {
  // Two-step modals print each button once in the banner; pick the rendered one.
  const index = await page.locator(selector).evaluateAll((nodes) => nodes.findIndex((node) => (node as HTMLElement).offsetParent !== null));
  expect(index, selector).toBeGreaterThanOrEqual(0);
  return `${selector} >> nth=${index}`;
}

async function snapshot(page: Page, selector: string): Promise<Record<string, string | number>> {
  return page.locator(selector).evaluate((element, properties) => {
    const style = getComputedStyle(element);
    const box = element.getBoundingClientRect();
    const out: Record<string, string | number> = { width: Math.round(box.width), height: Math.round(box.height) };
    for (const property of properties) {
      out[property] = style.getPropertyValue(property);
    }
    return out;
  }, PROPERTIES);
}

for (const viewport of VIEWPORTS) {
  test.describe(`equal prominence ${viewport.width}px`, () => {
    test.use({ viewport });

    test('reject and accept are filled the same in every theme, at rest and on hover', async ({ page }) => {
      await page.context().clearCookies();
      await page.goto('/en/');
      await expect(page.locator('#cookieconsent')).toBeVisible({ timeout: 10000 });

      for (const brand of [false, true]) {
        for (const theme of THEMES) {
          await page.evaluate(
            ({ theme, brand }) => {
              const modal = document.getElementById('cookieconsent')!;
              for (const name of [...modal.classList]) {
                if (name.startsWith('nowo-cookie-consent--theme-') || name === 'nowo-cookie-consent--dark-mode') {
                  modal.classList.remove(name);
                }
              }
              modal.classList.add('nowo-cookie-consent--equal-weight-buttons', 'nowo-cookie-consent--no-transitions');
              for (const name of theme.split(' ').filter(Boolean)) {
                modal.classList.add(name);
              }
              document.documentElement.style.setProperty('--nowo-ui-primary', brand ? '#1f6f54' : '');
              document.documentElement.style.setProperty('--nowo-ui-primary-contrast', brand ? '#ffffff' : '');
            },
            { theme, brand },
          );
          const reject = await visible(page, REJECT);
          const accept = await visible(page, ACCEPT);
          const label = `theme "${theme}" brand ${brand}`;

          await page.mouse.move(0, 0);
          const rejectRest = await snapshot(page, reject);
          const acceptRest = await snapshot(page, accept);
          expect(rejectRest, `${label} at rest`).toEqual(acceptRest);

          await page.locator(reject).hover();
          const rejectHover = await snapshot(page, reject);
          await page.locator(accept).hover();
          const acceptHover = await snapshot(page, accept);
          expect(rejectHover, `${label} on hover`).toEqual(acceptHover);
          expect(rejectHover['background-color'], `${label}: hover changes the fill`).not.toBe(rejectRest['background-color']);
        }
      }
    });
  });
}
