/**
 * Conditionally inject modal CSS.
 *
 * When a host links the standalone stylesheet (CSP style-src nonces block
 * injected <style> tags), skip runtime injection.
 */

import cssText from './cookie-consent.css?inline';

const INJECTED_STYLE_ID = 'nowo-cookie-consent-injected-css';

/**
 * Returns true when the host supplies CSS via <link> or a data attribute.
 */
export function shouldUseExternalCss(modalElement: HTMLElement | null = null): boolean {
  if (typeof document === 'undefined') {
    return false;
  }

  if (document.querySelector('link[data-nowo-cookie-consent-css]')) {
    return true;
  }

  if (document.documentElement.dataset.nowoCookieConsentExternalCss === 'true') {
    return true;
  }

  const modal = modalElement ?? document.getElementById('cookieconsent');

  return modal?.dataset.nowoExternalCss === 'true';
}

/**
 * Injects bundled CSS into <head> unless the host opted into external CSS.
 */
export function injectCookieConsentStyles(modalElement: HTMLElement | null = null): void {
  if (shouldUseExternalCss(modalElement)) {
    return;
  }

  if (document.getElementById(INJECTED_STYLE_ID)) {
    return;
  }

  const style = document.createElement('style');
  style.id = INJECTED_STYLE_ID;
  style.setAttribute('data-nowo-cookie-consent-injected', '');
  style.textContent = cssText;
  document.head.appendChild(style);
}
