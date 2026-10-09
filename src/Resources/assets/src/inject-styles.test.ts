import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('./cookie-consent.css?inline', () => ({
  default: '.nowo-cookie-consent{color:red}',
}));

describe('injectCookieConsentStyles', () => {
  beforeEach(() => {
    vi.resetModules();
    document.head.innerHTML = '';
    document.body.innerHTML = '';
    document.querySelectorAll('script[nonce]').forEach((el) => el.remove());
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

  it('copies the CSP nonce from <meta name="csp-nonce"> onto the injected style', async () => {
    const meta = document.createElement('meta');
    meta.setAttribute('name', 'csp-nonce');
    meta.setAttribute('content', 'meta-nonce');
    document.head.appendChild(meta);

    const { injectCookieConsentStyles, resolveCspNonce } = await import('./inject-styles');

    expect(resolveCspNonce()).toBe('meta-nonce');
    injectCookieConsentStyles();
    const style = document.getElementById('nowo-cookie-consent-injected-css') as HTMLStyleElement;
    expect(style.getAttribute('nonce')).toBe('meta-nonce');
  });

  it('falls back to the nonce of an existing nonced script', async () => {
    const script = document.createElement('script');
    script.setAttribute('nonce', 'script-nonce');
    document.body.appendChild(script);

    const { injectCookieConsentStyles } = await import('./inject-styles');

    injectCookieConsentStyles();
    const style = document.getElementById('nowo-cookie-consent-injected-css') as HTMLStyleElement;
    expect(style.getAttribute('nonce')).toBe('script-nonce');
  });

  it('uses the loader script nonce captured at module evaluation', async () => {
    const script = document.createElement('script');
    script.setAttribute('nonce', 'loader-nonce');
    Object.defineProperty(document, 'currentScript', { configurable: true, get: () => script });

    try {
      const { resolveCspNonce } = await import('./inject-styles');
      expect(resolveCspNonce()).toBe('loader-nonce');
    } finally {
      Reflect.deleteProperty(document, 'currentScript');
    }
  });

  it('omits the nonce attribute when no nonce is available', async () => {
    const { injectCookieConsentStyles, resolveCspNonce } = await import('./inject-styles');

    expect(resolveCspNonce()).toBe('');
    injectCookieConsentStyles();
    expect(document.getElementById('nowo-cookie-consent-injected-css')?.hasAttribute('nonce')).toBe(false);
  });
});
