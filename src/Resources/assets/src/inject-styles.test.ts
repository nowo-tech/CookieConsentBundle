import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('./cookie-consent.css?inline', () => ({
  default: '.nowo-cookie-consent{color:red}',
}));

describe('injectCookieConsentStyles', () => {
  beforeEach(() => {
    vi.resetModules();
    document.head.innerHTML = '';
    document.body.innerHTML = '';
    delete document.documentElement.dataset.nowoCookieConsentExternalCss;
  });

  it('injects a style tag when no external CSS marker is present', async () => {
    const { injectCookieConsentStyles, shouldUseExternalCss } = await import('./inject-styles');

    expect(shouldUseExternalCss()).toBe(false);
    injectCookieConsentStyles();

    const style = document.getElementById('nowo-cookie-consent-injected-css');
    expect(style).not.toBeNull();
    expect(style?.tagName).toBe('STYLE');
    expect(style?.textContent).toContain('.nowo-cookie-consent');
  });

  it('skips injection when link[data-nowo-cookie-consent-css] exists', async () => {
    const link = document.createElement('link');
    link.setAttribute('data-nowo-cookie-consent-css', '');
    document.head.appendChild(link);

    const { injectCookieConsentStyles, shouldUseExternalCss } = await import('./inject-styles');

    expect(shouldUseExternalCss()).toBe(true);
    injectCookieConsentStyles();
    expect(document.getElementById('nowo-cookie-consent-injected-css')).toBeNull();
  });

  it('skips injection when html data-nowo-cookie-consent-external-css is true', async () => {
    document.documentElement.dataset.nowoCookieConsentExternalCss = 'true';

    const { injectCookieConsentStyles, shouldUseExternalCss } = await import('./inject-styles');

    expect(shouldUseExternalCss()).toBe(true);
    injectCookieConsentStyles();
    expect(document.getElementById('nowo-cookie-consent-injected-css')).toBeNull();
  });

  it('skips injection when modal data-nowo-external-css is true', async () => {
    document.body.innerHTML = '<div id="cookieconsent" data-nowo-external-css="true"></div>';

    const { injectCookieConsentStyles, shouldUseExternalCss } = await import('./inject-styles');
    const modal = document.getElementById('cookieconsent');

    expect(shouldUseExternalCss(modal)).toBe(true);
    injectCookieConsentStyles(modal);
    expect(document.getElementById('nowo-cookie-consent-injected-css')).toBeNull();
  });

  it('does not inject twice', async () => {
    const { injectCookieConsentStyles } = await import('./inject-styles');

    injectCookieConsentStyles();
    injectCookieConsentStyles();

    expect(document.querySelectorAll('#nowo-cookie-consent-injected-css')).toHaveLength(1);
  });
});
