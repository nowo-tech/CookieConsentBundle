/**
 * Conditionally inject modal CSS.
 *
 * When a host links the standalone stylesheet, skip runtime injection. Otherwise the
 * injected <style> copies the CSP nonce of the loader script (nowo-tech `csp_nonce`
 * convention) so it survives `style-src-elem 'nonce-…'` policies.
 */

import cssText from './cookie-consent.css?inline';

const INJECTED_STYLE_ID = 'nowo-cookie-consent-injected-css';

/**
 * Nonce of the <script> that loaded this bundle, captured while it executes
 * (document.currentScript is null inside later callbacks such as DOMContentLoaded).
 */
const LOADER_SCRIPT_NONCE = readCurrentScriptNonce();

function readCurrentScriptNonce(): string {
  if (typeof document === 'undefined') {
    return '';
  }

  const script = document.currentScript as HTMLScriptElement | null;

  return script?.nonce || script?.getAttribute('nonce') || '';
}

/**
 * Resolves the CSP nonce to copy onto injected elements.
 *
 * Order: loader script (document.currentScript) → <meta name="csp-nonce"> → first nonced <script>.
 * Browsers hide the nonce content attribute, so the IDL `nonce` property is read first.
 */
export function resolveCspNonce(): string {
  if (LOADER_SCRIPT_NONCE !== '') {
    return LOADER_SCRIPT_NONCE;
  }

  if (typeof document === 'undefined') {
    return '';
  }

  const meta = document.querySelector<HTMLMetaElement>('meta[name="csp-nonce"]');
  const metaNonce = meta?.nonce || meta?.getAttribute('nonce') || meta?.content || '';
  if (metaNonce !== '') {
    return metaNonce;
  }

  const script = document.querySelector<HTMLScriptElement>('script[nonce]');

  return script?.nonce || script?.getAttribute('nonce') || '';
}

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
  const nonce = resolveCspNonce();
  if (nonce !== '') {
    style.nonce = nonce;
    style.setAttribute('nonce', nonce);
  }
  style.textContent = cssText;
  document.head.appendChild(style);
}
