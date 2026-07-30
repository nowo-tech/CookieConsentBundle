/**
 * Symfony SameOrigin CSRF helpers for XHR consent posts.
 *
 * Mirrors the Flex csrf_protection Stimulus controller: when the CSRF field still
 * holds the cookie-name placeholder (stateless token ids), generate a random token,
 * double-submit it via cookie, and expose it for the csrf-token request header.
 */

const NAME_CHECK = /^[-_a-zA-Z0-9]{4,22}$/;
const TOKEN_CHECK = /^[-_/+a-zA-Z0-9]{24,}$/;

interface MsCrypto {
  getRandomValues: Crypto['getRandomValues'];
}

declare global {
  interface Window {
    msCrypto?: MsCrypto;
  }
}

/**
 * Locates the Symfony CSRF hidden field on a consent form.
 */
export function findCsrfField(form: HTMLFormElement): HTMLInputElement | null {
  return form.querySelector<HTMLInputElement>(
    'input[data-controller="csrf-protection"], input[name="_csrf_token"], input[name="_token"]',
  );
}

function randomToken(): string {
  const cryptoApi = window.crypto ?? window.msCrypto;
  if (!cryptoApi) {
    throw new Error('Secure random generator is unavailable.');
  }

  const bytes = cryptoApi.getRandomValues(new Uint8Array(18));

  return btoa(String.fromCharCode(...Array.from(bytes)));
}

/**
 * Prepares Symfony CSRF for an XHR POST (native submit never fires).
 *
 * @returns Token value suitable for the `csrf-token` header when double-submit applies.
 */
export function prepareCsrfForRequest(form: HTMLFormElement): string | null {
  const csrfField = findCsrfField(form);
  if (!csrfField) {
    return null;
  }

  let csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');
  let csrfToken = csrfField.value;

  // Stateless SameOrigin placeholder: field value is the cookie name (e.g. "csrf-token").
  if (!csrfCookie && NAME_CHECK.test(csrfToken)) {
    csrfCookie = csrfToken;
    csrfField.setAttribute('data-csrf-protection-cookie-value', csrfCookie);
    csrfToken = randomToken();
    csrfField.value = csrfToken;
    csrfField.defaultValue = csrfToken;
  }

  if (csrfCookie && TOKEN_CHECK.test(csrfToken)) {
    const cookie = `${csrfCookie}_${csrfToken}=${csrfCookie}; path=/; samesite=strict`;
    document.cookie = window.location.protocol === 'https:' ? `__Host-${cookie}; secure` : cookie;
  }

  return TOKEN_CHECK.test(csrfField.value) ? csrfField.value : null;
}
